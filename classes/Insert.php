<?php

namespace MOJElasticSearch;

use stdClass;

class Insert extends ElasticSearch
{
    public $allowed_post_types = [];

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
            //return false;
            self::debug('The post item status does not equal publish', $status, true);
        }

        $document = get_post($post_id);

        if (is_wp_error($document)) {
            //return false;
            self::debug('Could not get ' . $document->post_type . ' post from WP', $document->get_error_message(), true);
        }

        if (!$this->client()) {
            //return false;
            self::debug('ES Client has not been created', $this->client(), true);
        }

        # checks complete... prepare the document and index
        $params = $this->_params($document, $document->post_type);
        self::debug('$params:', $params, true);
        $response = $this->client()->index($params);
        self::debug('Index Response:', $response, true);
    }

    public function bulk()
    {
        if (!isset($_GET["create-index"])) {
            return null;
        }

        foreach ($this->allowed_post_types as $type) {
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
                $params = $this->_params($item, $type, false);

                // Every 1000 documents stop and send the bulk request
                if ($key % 1000 == 0) {
                    $responses = $this->client()->bulk($params);

                    // erase the old bulk request
                    $params = ['body' => []];

                    // unset the bulk response when you are done to save memory
                    unset($responses);
                }
            }
        }

        // Send the last batch if it exists
        if (!empty($params['body'])) {
            $responses = $this->client()->bulk($params);
        }
    }

    private function _params($object, $type, $is_single = true)
    {
        $params = [];
        $id = (string)$object->ID;

        $body = [
            'ID' => $id,
            'post_date' => $object->post_date,
            'post_content' => sanitize_text_field($object->post_content),
            'post_title' => $object->post_title,
            'post_excerpt' => ($object->post_excerpt || new stdClass())
        ];

        if ($is_single) {
            return [
                'index' => ES_INDEX . '-' . $type,
                'id'    => $id,
                'body'  => $body
            ];
        }

        $params['body'][] = [
            'index' => [
                '_index' => ES_INDEX . '-' . $type,
                "_id" => $id
            ]
        ];

        $params['body'][] = $body;

        return $params;
    }
}
