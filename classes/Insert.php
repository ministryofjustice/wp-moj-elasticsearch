<?php

namespace MOJElasticSearch;

use stdClass;

class Insert extends ElasticSearch
{
    public function __construct()
    {
        $this->actions();

        parent::__construct();
    }

    public function actions()
    {
        # ACTION HOOKS
        add_action('save_post', [$this, 'document']);
        add_action('plugins_loaded', [$this, 'bulk']);
    }

    public function document($post_id)
    {
        if ($parent_id = wp_is_post_revision($post_id)) {
            $post_id = $parent_id;
        }

        # discard all but publish
        $status = get_post_status($post_id);
        if ($status !== 'publish') {
            return false;
        }

        $document = get_post($post_id);

        if (is_wp_error($document)) {
            return false;
        }

        if (!$this->client()) {
            return false;
        }

        # checks complete... prepare the document and index
        $params = $this->_params($document, $document->post_type);
        try {
            $response = $this->client()->index($params);
        } catch (\Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
    }

    public function bulk()
    {
        $options = Admin::options('moj_es');
        if (!isset($options['bulk_activate']) || empty($options['bulk_activate']) || $options['bulk_activate'] === 'no') {
            return false;
        }

        # reset value
        $options['bulk_activate'] = null;
        update_option('moj_es' . Admin::OPTION_NAME, $options);

        $count = 0;
        $types = $options['bulk_post_types'] ?? [];
        foreach ($types as $type) {
            $posts = get_posts([
                'post_type' => $type,
                'cache_results' => false,
                'post_status' => ['publish'],
                'numberposts' => -1
            ]);

            $params = [
                'body' => []
            ];

            foreach ($posts as $key => $item) {
                $params = $this->_params($item, $type, $params);

                // Every 1000 documents stop and send the bulk request
                if ($count % 1000 == 0) {
                    try {
                        $responses = $this->client()->bulk($params);
                    } catch (\Exception $e) {
                        trigger_error($e->getMessage(), E_USER_ERROR);
                    }

                    // erase the old bulk request
                    $params = ['body' => []];

                    // unset the bulk response when you are done to save memory
                    unset($responses);
                }
                $count++;
            }

            // Send the last batch if it exists
            if (!empty($params['body'])) {
                try {
                    $responses = $this->client()->bulk($params);
                } catch (\Exception $e) {
                    trigger_error($e->getMessage(), E_USER_ERROR);
                }
            }

            # sleep
            sleep(5);
        }
    }

    private function _params($object, $type, $bulk = false)
    {
        $id = (string)$object->ID;

        $body = [
            'ID' => $id,
            'post_date' => $object->post_date,
            'post_content' => sanitize_text_field($object->post_content),
            'post_title' => $object->post_title,
            'post_excerpt' => ($object->post_excerpt || new stdClass())
        ];

        if (!$bulk) {
            return [
                'index' => ES_INDEX . '-' . $type,
                'id' => $id,
                'body' => $body
            ];
        }

        $bulk['body'][] = [
            'index' => [
                '_index' => ES_INDEX . '-' . $type,
                "_id" => $id
            ]
        ];

        $bulk['body'][] = $body;

        return $bulk;
    }
}
