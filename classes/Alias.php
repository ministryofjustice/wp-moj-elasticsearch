<?php

namespace MOJElasticSearch;

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
        $bulk_is_active = get_option('_moj_es_bulk_index_active', false);

        if ($bulk_is_active && ($this->isESQueueEmpty() && wp_next_scheduled('moj_es_poll_for_completion'))) {
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
                trigger_error('MoJ ES  W A R N I N G: ' . $response->get_error_message() . '.');
                return false;
            }

            // no error... let's create a task to remove the index safely in 60 days
            if ($safe_delete) {
                $this->scheduleDeletion($index_old);
            }

            // set active to false
            delete_option('_moj_es_bulk_index_active');
        }

        return $index_updated;
    }

    /**
     * Checks if the indexing process completed naturally and if so, schedules
     * a cron task to update our alias index routes
     *
     * @return bool
     */
    public function pollForCompletion()
    {
        if (false !== get_transient('moj_es_index_force_stopped')) {
            $stats = $this->admin->getStats();
            $stats['force_stop'] = true;
            $this->admin->setStats($stats);
        }

        if ($this->admin->maybeAllItemsIndexed()) {
            // schedule a task to complete the index process
            if (!wp_next_scheduled('moj_es_poll_for_completion')) {
                wp_schedule_event(
                    time(),
                    $this->admin->cronInterval('every_minute'),
                    'moj_es_poll_for_completion'
                );
            }

            return true;
        }

        wp_mail(get_option('admin_email'), 'Shutdown by user', $this->debug('FORCED', 'BOOHOO'));

        return null;
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
        $json = json_decode(wp_remote_retrieve_body($response));

        if (is_array($json)) {
            $json = $json[0];
            if (property_exists($json, 'queue')) {
                if ($json->active === "0" && $json->queue === "0") {
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
            '[MOJ ES W A R N I N G] Index will die in 60 days',
            $this->debug(
                'INDEX HAS BEEN SCHEDULED FOR DELETE',
                'Nb. ' . $index . ' to be removed on ' . date("F j, Y, g:i a", $sixty_days) .
                ' ... you may prevent the action by deleting the task: wp cron event delete moj_es_delete_index'
            )
        );
    }
}
