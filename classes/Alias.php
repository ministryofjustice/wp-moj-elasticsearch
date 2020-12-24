<?php

namespace MOJElasticSearch;

use Exception;

class Alias
{
    use Debug;

    private $url;
    public $name;
    public $index;

    /**
     * @var Admin
     */
    private $admin;

    public function __construct(Admin $admin)
    {
        $this->admin = $admin;
        $this->url = get_option('ep_host') . '_aliases';
        $this->name = get_option('_moj_es_alias_name');

        $this->hooks();
    }

    /**
     * @return string
     */
    public function add()
    {
        return '';
    }

    public function hooks()
    {
        add_action('moj_es_poll_for_completion', [$this, 'update']);
        add_action('moj_es_delete_index', [$this, 'deleteIndex']);
    }

    /**
     * @return bool|object
     */
    public function update()
    {
        $index_updated = false;

        if ($this->isESQueueEmpty() && wp_next_scheduled('moj_es_poll_for_completion')) {
            // prevent cron hook from running:
            $timestamp = wp_next_scheduled('moj_es_poll_for_completion');
            wp_unschedule_event($timestamp, 'moj_es_poll_for_completion');

            $index_old = get_option('_moj_es_index_name');
            $index_new = get_option('_moj_es_new_index_name');

            // cache the old index name for deletion
            $safe_delete = true; // in case of first run
            update_option('_moj_es_index_to_delete', $index_old);

            $template = 'update.json'; // this could have multiple [add.json, delete.json]

            // first run
            if ($index_new === $index_old) {
                $template = 'add.json';
                $safe_delete = false; // we cannot delete the index we have just created
            }

            // maybe the old index doesn't exist on alias?
            $alias_indexes = $this->getAliasIndexes();
            if (!in_array($index_old, $alias_indexes)) {
                $template = 'add.json';
            }

            // track the newly created index
            $index_updated = update_option('_moj_es_index_name', $index_new);

            $search = ['[OLD]', '[NEW]', '[ALIAS]'];
            $replace = [$index_old, $index_new, $this->name];

            $body = str_replace(
                $search,
                $replace,
                file_get_contents(__DIR__ . '/../assets/json/alias-' . $template)
            );

            $args = [
                'headers' => ["Content-Type" => "application/json"],
                'body' => $body
            ];

            $response = wp_safe_remote_post($this->url, $args);

            // clear new index name
            update_option('_moj_es_new_index_name', null);

            if (is_wp_error($response)) {
                trigger_error('[MOJ ES WARNING] ' . $response->get_error_message() . '.');
                return false;
            }

            // no error... let's create a task to remove the index safely in 60 days
            if ($safe_delete) {
                $this->scheduleDeletion($index_old);
            }
        }

        return $index_updated;
    }

    /**
     * Checks if the indexing process completed naturally and if so, schedules
     * a cron task to update the alias index routes.
     *
     * Stats are passed purely for tracking execution.
     *
     * @param $stats
     * @return bool
     * @throws Exception
     */
    public function pollForCompletion(&$stats)
    {
        // bail if force stopped
        if (false !== get_transient('moj_es_index_force_stopped')) {
            $stats['force_stop'] = true;
            wp_mail(get_option('admin_email'), 'Index shutdown by user', $this->debug('FORCED', 'BOOHOO'));
            throw new Exception('Indexing was FORCE STOPPED. Switching alias indexes was prevented.');
        }

        // bail if a WordPress user sent an update
        if (false == get_option('_moj_es_bulk_index_active')) {
            throw new Exception('No need to update the alias. The request is coming from WordPress.');
        }

        $this->admin->message('The type and value of _moj_es_bulk_index_active is: ' . gettype(get_option('_moj_es_bulk_index_active')) . ' | ' . (get_option('_moj_es_bulk_index_active') ? 'true' : 'false'), $stats);

        // check confidence to switch index
        if ($this->admin->maybeAllItemsIndexed($stats)) {
            // schedule a task to complete the index process
            if (false == wp_next_scheduled('moj_es_poll_for_completion')) {
                wp_schedule_event(
                    time(),
                    $this->admin->cronInterval('every_minute'),
                    'moj_es_poll_for_completion'
                );
                return true;
            }
            throw new Exception('Poll for completion schedule was already set. The task to update the alias is running.');
        }
        throw new Exception('The type and value of _moj_es_bulk_index_active is: ' . gettype(get_option('_moj_es_bulk_index_active')) . ' | ' . get_option('_moj_es_bulk_index_active'));
        throw new Exception('CRON to switch alias prevented. Confidence of completed index was too low.');
    }

    public function deleteIndex()
    {
        $cached_index_old = get_option('_moj_es_index_to_delete');
        if (!$cached_index_old) {
            return;
        }

        $response = wp_safe_remote_request(get_option('EP_HOST') . $cached_index_old, ['method' => 'DELETE']);
        if (!is_wp_error($response)) {
            delete_option('_moj_es_index_to_delete');
            wp_clear_scheduled_hook('moj_es_delete_index');
        }
    }

    public function isESQueueEmpty()
    {
        $url = get_option('EP_HOST') . '_cat/thread_pool/write?v&h=active,queue&format=json';
        $response = wp_safe_remote_get($url);
        $process = json_decode(wp_remote_retrieve_body($response));

        if (is_array($process)) {
            $process = $process[0];
            if (property_exists($process, 'queue')) {
                if ($process->active === "0" && $process->queue === "0") {
                    return true;
                }
            }
        }

        return false;
    }

    private function scheduleDeletion($index)
    {
        $sixty_days = strtotime('+60 days');
        wp_clear_scheduled_hook('moj_es_delete_index');
        wp_schedule_single_event($sixty_days, 'moj_es_delete_index');
        wp_mail(
            get_option('admin_email'),
            '[MOJ ES WARNING] Unused index will die in 60 days',
            $this->debug(
                'INDEX HAS BEEN SCHEDULED FOR DELETE',
                'Nb. ' . $index . ' to be removed on ' . date("F j, Y, g:i a", $sixty_days) .
                ' ... you may prevent the action by deleting the task: wp cron event delete moj_es_delete_index'
            )
        );
    }

    public function getAliasIndexes()
    {
        if (false === ($alias_indexes = get_transient('_moj_es_alias_indexes'))) {
            $host = get_option('EP_HOST');
            $alias = get_option('_moj_es_alias_name');
            $url = $host . '_cat/aliases/' . $alias . '?v&format=json&h=index';
            $response = wp_safe_remote_get($url);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                $alias_indexes = json_decode(wp_remote_retrieve_body($response));
                $indexes = [];
                foreach ($alias_indexes as $alias_index) {
                    $indexes[] = $alias_index->index;
                }
                set_transient('_moj_es_alias_indexes', $indexes, 2 * MINUTE_IN_SECONDS);
                $alias_indexes = $indexes;
            }
        }

        return $alias_indexes;
    }
}
