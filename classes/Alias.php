<?php

namespace MOJElasticSearch;

use MOJElasticSearch\Index as Index;

class Alias
{
    use Debug;

    private $url;
    public $name;
    public $index;

    public function __construct()
    {
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
    }

    /**
     * @return bool|object
     */
    public function update()
    {
        $index_updated = false;
        $queue_is_empty = $this->isESQueueEmpty();

        if ($queue_is_empty && wp_next_scheduled('moj_es_poll_for_completion')) {
            // prevent cron hook from running:
            $timestamp = wp_next_scheduled('moj_es_poll_for_completion');
            wp_unschedule_event($timestamp, 'moj_es_poll_for_completion');

            $index_old = get_option('_moj_es_index_name');
            $index_new = get_option('_moj_es_new_index_name');

            $debugging = $this->debug('OLD INDEX NAME', $index_old);
            $debugging .= $this->debug('NEW INDEX NAME', $index_new);

            $template = 'update.json'; // this could have multiple [add.json, delete.json]

            // first run
            if ($index_new === $index_old) {
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

            $debugging .= $this->debug('_moj_es_index_name', get_option('_moj_es_index_name'));
            $debugging .= $this->debug('_moj_es_new_index_name', get_option('_moj_es_new_index_name'));

            wp_mail('me@debuggin.com', 'DEBUGGING', $debugging);

            if (is_wp_error($response)) {
                trigger_error('MoJ ES  W A R N I N G: ' . $response->get_error_message() . '.');
                return false;
            }

            // no error... let's remove the old index
            wp_safe_remote_request(get_option('EP_HOST') . $index_old, ['method' => 'DELETE']);
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
        if (false === get_transient('moj_es_index_force_stopped')) {
            // schedule a task to clean up the index once it has finished
            if (!wp_next_scheduled('moj_es_poll_for_completion')) {
                wp_schedule_event(time(), 'one_minute', 'moj_es_poll_for_completion');
            }

            return true;
        }

        wp_mail('me@forced.com', 'Shutdown by user', $this->debug('FORCED', 'BOOHOO'));

        return null;
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
}
