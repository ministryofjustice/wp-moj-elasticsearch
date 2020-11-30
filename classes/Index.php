<?php

namespace MOJElasticSearch;

use WP_Error;
use MOJElasticSearch\Settings\Page;
use MOJElasticSearch\Settings\IndexSettings;

/**
 * Class Index
 * @package MOJElasticSearch
 * @SuppressWarnings(PHPMD)
 */
class Index extends Page
{
    /**
     * This class requires settings fields in the plugins dashboard.
     * Include the Settings trait
     */
    use Debug;

    private $stat_output_echo = true;

    /**
     * Cache the name of a bulk index route
     * @var string|null name
     */
    public $index_name_current = '';

    /**
     * Cache the NEW name of a bulk index route
     * @var string|null name
     */
    public $index_name_new = null;

    /**
     * Alias object
     * @var Alias
     */
    private $alias;

    /**
     * Admin object
     * @var Admin
     */
    private $admin;

    /**
     * @var IndexSettings
     */
    private $settings;

    public function __construct(IndexSettings $settings)
    {
        parent::__construct();

        // construct
        $this->alias = new Alias();
        $this->admin = $settings->admin;
        $this->settings = $settings;

        // smaller server on dev
        if ($this->admin->env === 'development') {
            $this->settings->payload_min = 6000000;
            $this->settings->payload_max = 8900000;
            $this->settings->payload_ep_max = 9900000;
        }

        $this->index_name_current = get_option('_moj_es_index_name');

        self::hooks();
    }

    /**
     * A place for all class specific hooks and filters
     */
    public function hooks()
    {
        add_filter('ep_pre_request_url', [$this, 'request'], 11, 5);
        add_action('moj_es_cron_hook', [$this, 'cleanUpIndexing']);
        add_action('plugins_loaded', [$this, 'cleanUpIndexingCheck']);
        add_action('wp_ajax_stats_load', [$this, 'getStatsHTML']);
        add_filter('ep_index_name', [$this, 'indexNames'], 11, 1);
        add_filter('ep_index_health_stats_indices', [$this, 'healthStatsIndex'], 10, 1);
    }

    /**
     * Modifies the URL if the request is performing a document index/update
     * @param $request
     * @param $failures
     * @param $host
     * @param $path
     * @param $args
     * @return string
     */
    public function request($request, $failures, $host, $path, $args): string
    {
        // reset intercept for normal requests.
        remove_filter('ep_intercept_remote_request', [$this, 'interceptTrue']);
        remove_filter('ep_do_intercept_request', [$this, 'requestIntercept'], 11);
        remove_filter('ep_do_intercept_request', [$this, 'requestInterceptFalsy'], 11);

        // do not disturb searching
        if (str_replace('/_search', '', $path) != $path) {
            // a search is being performed - get the alias, standardise the URL.
            return $host . $this->alias->name . '/_search';
        }

        // schedule a task to clean up the index once it has finished
        if (!wp_next_scheduled('moj_es_cron_hook')) {
            wp_schedule_event(time(), 'one_minute', 'moj_es_cron_hook');
        }

        $allow_methods = [
            'POST',
            'PUT'
        ];

        $disallow_paths = [
            '/_aliases',
            '_stats',
            '_ingest/pipeline',
        ];

        $disallow_payloads = [
            '{"settings":'
        ];

        if (str_replace($disallow_paths, '', $path) != $path) {
            return $request;
        }

        if (isset($args['body'])) {
            if (str_replace($disallow_payloads, '', $args['body']) != $args['body']) {
                return $request;
            }
        }

        if (isset($args['method']) && in_array($args['method'], $allow_methods)) {
            $request = $this->index($request, $failures, $host, $path, $args);
        }

        return $request;
    }

    /**
     * This method fires after the alias has been set in \ElasticPressHooks
     * If the system is currently indexing, the alias is returned.
     * Hooked into filter 'ep_index_name'
     *
     * @param string $alias_name
     * @return string
     */
    public function indexNames(string $alias_name): string
    {
        if (!$this->admin->isIndexing()) {
            return $alias_name;
        }

        return $this->getIndexName();
    }

    /**
     * Get the new name for building a fresh index
     * Hooked into filter 'ep_index_name' via $this->indexNames()
     *
     * @return string
     */
    private function getIndexName(): string
    {
        if (!empty($this->index_name_new) && $this->index_name_new === $this->index_name_current) {
            $this->index_name_new = null;
        }

        if (!empty($this->index_name_new) || !empty(($this->index_name_new = get_option('_moj_es_new_index_name')))) {
            return $this->index_name_new;
        }

        echo $this->debug("CURRENT Index", $this->index_name_current);
        echo $this->debug("NEW Index", $this->index_name_new);

        // index names
        $index_names = [
            'mythical' => [
                'afanc', 'alphyn', 'amphiptere', 'basilisk', 'bonnacon', 'cockatrice', 'crocotta', 'dragon', 'griffin',
                'hippogriff', 'mandragora', 'manticore', 'melusine', 'ouroboros', 'salamander', 'woodwose'
            ],
            'knight' => [
                'bagdemagus', 'bedivere', 'bors', 'brunor', 'cliges', 'caradoc', 'dagonet', 'daniel', 'dinadan',
                'galahad', 'galehaut', 'geraint', 'griflet', 'lamorak', 'lancelot', 'lanval', 'lionel', 'moriaen',
                'palamedes', 'pelleas', 'pellinore', 'percival', 'sagramore', 'tristan'
            ]
        ];

        $new_index_names = array_rand($index_names); // string
        $new_index_key = array_rand($index_names[$new_index_names]); // int
        $new_index = $index_names[$new_index_names][$new_index_key]; // string

        if ($new_index === $this->index_name_current) {
            $new_index = $this->getIndexName();
        }

        // intranet.local[.rob].basilisk
        $namespace = (function_exists('env') ? env('ES_ALIAS_NAMESPACE') : null);
        $new_index = $this->alias->name . "." . ($namespace ? $namespace . "." : "") . $new_index;

        // first run
        if (!$this->index_name_current) {
            update_option('_moj_es_index_name', $new_index);
        }

        update_option('_moj_es_new_index_name', $new_index);

        return $new_index;
    }

    /**
     * Index management method.
     * Given AWS limitations; this method makes decisions on how to index posts based on payload size.
     * If the payload is small, it is appended to a bulk file for later indexing
     * If the payload is large, it is sent as a single payload to ES
     * If the payload is too large (exceeds AWS limits), a record of the failure is logged and indexing continues
     * Once the bulk file reaches a predefined size, it is sent as a bulk payload to ES
     * @param $request
     * @param $failures
     * @param $host
     * @param $path
     * @param $args
     * @return string
     */
    public function index($request, $failures, $host, $path, $args): string
    {
        if ($this->sendBulk($args['body'])) {
            return $request;
        }

        $stats = $this->getStats();

        // check the total size - no more than max defined
        if (mb_strlen($args['body'], 'UTF-8') <= $this->settings->payload_ep_max) {
            // allow ElasticPress to index normally
            $stats['total_large_requests']++;
            $this->setStats($stats);
            return $request;
        }

        // we have a large file
        $post_id = json_decode(trim(strstr($args['body'], "\n", true)));
        array_push($stats['large_files'], $post_id);
        $this->setStats($stats);

        add_filter('ep_intercept_remote_request', [$this, 'interceptTrue']);
        add_filter('ep_do_intercept_request', [$this, 'requestInterceptFalsy'], 11, 4);

        return $request;
    }

    /**
     * See $this->index() for information
     * Returning false from this method passes indexing back to EP, but only if the payload size is
     * found not to be too large later on
     * @param $body
     * @return bool
     */
    public function sendBulk($body)
    {
        $stats = $this->getStats();
        $body_new_size = mb_strlen($body, 'UTF-8');
        $body_stored_size = 0;

        if (file_exists($this->importLocation() . 'moj-bulk-index-body.json')) {
            $body_stored_size = mb_strlen(
                file_get_contents($this->importLocation() . 'moj-bulk-index-body.json')
            );
        }

        $stats['bulk_body_size'] = $this->admin->humanFileSize($body_stored_size);

        // payload maybe too big?
        if ($body_stored_size + $body_new_size > $this->settings->payload_max) {
            return false; // index individual file (normal)
        }

        // add body to bodies
        $this->writeBodyToFile($body);

        $this->setStats($stats);

        add_filter('ep_intercept_remote_request', [$this, 'interceptTrue']);

        if ($body_stored_size + $body_new_size > $this->settings->payload_min) {
            // prepare interception
            add_filter('ep_do_intercept_request', [$this, 'requestIntercept'], 11, 4);
            return true;
        }

        add_filter('ep_do_intercept_request', [$this, 'requestInterceptFalsy'], 11, 4);
        return true;
    }

    /**
     * Append a string to the end of a file and return the new length, or false on failure
     * @param string $body
     * @return false|int
     */
    public function writeBodyToFile(string $body)
    {
        $path = $this->importLocation() . 'moj-bulk-index-body.json';
        $handle = fopen($path, 'a');
        fwrite($handle, trim($body) . "\n");
        fclose($handle);

        return mb_strlen(file_get_contents($path));
    }

    /**
     * Make a bulk request to Elasticsearch and return the response, or WP_Error on failure
     * Before this method is called, we tell EP not to make the request. By doing this we intercept the
     * normal flow of indexing and make the request here. This allows us to collect and send 'size orientated' payloads.
     *
     * @param $request
     * @param $query
     * @param $args
     * @param $failures
     * @return array|WP_Error
     * @uses add_filter('ep_do_intercept_request');
     *
     * @uses add_filter('ep_intercept_remote_request', true);
     */
    public function requestIntercept($request, $query, $args, $failures)
    {
        $args['body'] = file_get_contents($this->importLocation() . 'moj-bulk-index-body.json');
        $args['timeout'] = 120; // for all requests

        $request = wp_remote_request($query['url'], $args);

        if (!is_wp_error($request)) {
            $stats = $this->getStats();
            unlink($this->importLocation() . 'moj-bulk-index-body.json');
            $stats['bulk_body_size'] = 0;

            $stats['total_bulk_requests'] = $stats['total_bulk_requests'] ?? 0;
            $stats['total_bulk_requests']++;
            $stats['last_url'] = $query['url'];
            $this->setStats($stats); // next, save request params
        }

        return $request;
    }

    /**
     * Intercept the normal flow of indexing and make a false request to Elasticsearch
     * Returns a structured array in the form of a successful wp_remote_request().
     *
     * When body data is appended to the bulk file for sending later, this method is called to indicate to EP that a
     * successful transmission to Elasticsearch was made. This allows normal processing to continue in EP
     *
     * @param $request
     * @param $query
     * @param $args
     * @param $failures
     * @return array
     * @uses add_filter('ep_do_intercept_request');
     *
     * @uses add_filter('ep_intercept_remote_request', true);
     */
    public function requestInterceptFalsy($request, $query, $args, $failures): array
    {
        $stats = $this->getStats();
        $stats['total_stored_requests'] = $stats['total_stored_requests'] ?? 0;
        $stats['total_stored_requests']++;
        $stats['last_url'] = $query['url'];

        // remove body from overall payload for reporting
        unset($args['body']);
        $args['timeout'] = 120; // for all requests
        $stats['last_args'] = $args;
        $this->setStats($stats);

        // mock response
        return array(
            'headers' => array(),
            'body' => file_get_contents(__DIR__ . '/../assets/json/mock.json'),
            'response' => array(
                'code' => 200,
                'message' => 'OK (falsy)',
            ),
            'cookies' => array(),
            'http_response' => [],
        );
    }

    /**
     * Method needed to remove_filter
     * @return bool
     */
    public function interceptTrue()
    {
        return true;
    }

    /**
     * Makes sure there's no data left in the bulk file once indexing has completed
     * @return bool
     */
    public function cleanUpIndexing()
    {
        if ($this->admin->isIndexing()) {
            return false;
        }

        $file_location = $this->importLocation() . 'moj-bulk-index-body.json';

        if (file_exists($file_location)) {
            if (filesize($file_location) > 0) {
                // send last batch of posts to index
                $stats = $this->getStats();
                $url = $stats['last_url'] ?? null;
                $args = $stats['last_args'] ?? null;

                if (!$url || !$args) {
                    $stats['cleanup_error'] = 'Cleanup cannot run. URL or ARGS not available in stats array';
                    $this->setStats($stats);
                    return false;
                }

                // local function ensure object is array
                $toArray = function ($x) use (&$toArray) {
                    return is_scalar($x) ? $x : array_map($toArray, (array)$x);
                };

                $this->requestIntercept(null, ['url' => $url], $toArray($args), null);

                // Poll for completion
                $this->alias->pollForCompletion();

                // stop timer
                $this->admin->indexTimer(time(), false);

                // now we are done, stop the cron hook from running:
                $timestamp = wp_next_scheduled('moj_es_cron_hook');
                wp_unschedule_event($timestamp, 'moj_es_cron_hook');
            }
        }

        return null;
    }

    /**
     * Manual clean of index process
     */
    public function cleanUpIndexingCheck()
    {
        $force_clean_up = $this->options()['force_clean_up'] ?? null;
        if ($force_clean_up) {
            $this->updateOption('force_clean_up', null);
            $this->cleanUpIndexing();
        }
    }

    public function getStatsHTML()
    {
        $this->options();
        $stats = $this->settings->indexStatisticsAjax();

        echo json_encode($stats);
        die();
    }
}
