<?php

namespace MOJElasticSearch;

use Aws\Lambda\Exception\LambdaException;
use Aws\S3\Exception\S3Exception;
use Exception;
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
    /**
     * @var string
     */
    private $bulk_index_body;
    private $index_storage;
    /**
     * @var array
     */
    private $payloads;

    public function __construct(IndexSettings $settings, Alias $alias)
    {
        parent::__construct();

        // construct
        $this->alias = $alias;
        $this->admin = $settings->admin;
        $this->settings = $settings;

        $this->payloads = $this->payloadSizes();
        $this->settings->payload_min = $this->payloads['min'];
        $this->settings->payload_max = $this->payloads['max'];
        $this->settings->payload_ep_max = $this->payloads['es_max'];

        $this->index_name_current = get_option('_moj_es_index_name');

        $this->bulk_index_body = fopen("php://memory", "r+");

        // old clean up check
        $this->cleanupIndexingCheck();

        self::hooks();
    }

    /**
     * A place for all class specific hooks and filters
     */
    public function hooks()
    {
        add_filter('ep_pre_request_url', [$this, 'request'], 11, 5);
        add_action('moj_es_cleanup_cron', [$this, 'cleanUpIndexing']);
        add_action('wp_ajax_stats_load', [$this, 'getStatsHTML']);
        add_filter('ep_index_name', [$this, 'indexNames'], 11, 1);
        //add_filter('ep_index_health_stats_indices', [$this, 'healthStatsIndex'], 10, 1);
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
            // schedule a task to clean up the index once it has finished
            if (!wp_next_scheduled('moj_es_cleanup_cron')) {
                wp_schedule_event(time(), $this->admin->cronInterval('every_ninety_seconds'), 'moj_es_cleanup_cron');
            }
            $request = $this->index($request, $failures, $host, $path, $args);
        }

        return $request;
    }

    /**
     * This method fires after the alias has been set in \ElasticPressHooks - as a direct result of priority
     * If the system is not indexing, the alias is returned.
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
        $namespace = (function_exists('env') ? env('ES_INDEX_NAMESPACE') : null);
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

        $stats = $this->admin->getStats();

        // check the total size - no more than max defined
        $object_size = mb_strlen($args['body'], 'UTF-8');
        if ($object_size <= $this->settings->payload_ep_max) {
            // allow ElasticPress to index normally
            $stats['total_large_requests']++;
            $this->admin->setStats($stats);
            return $request;
        }

        // we have a large file
        $post_id = json_decode(trim(strstr($args['body'], "\n", true)));
        array_push($stats['large_files'], $post_id);
        $this->admin->setStats($stats);

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
        $stats = $this->admin->getStats();
        $body_new_size = mb_strlen($body, 'UTF-8');

        // payload maybe too big?
        if ($stats['bulk_body_size_bytes'] + $body_new_size > $this->settings->payload_max) {
            return false; // index individual file (normal)
        }

        // for lambda:
        $async = false;
        if ($this->index_storage === 'lambda') {
            // payload maybe too big?
            if ($body_new_size > $this->payloads['max_lambda']) {
                return false; // index individual file (normal)
            }

            if ($body_new_size < $this->payloads['max_lambda_async']) {
                $async = true;
            }
        }

        // add body to bodies
        $this->writeBodyToS3($body, $async);

        $stats['bulk_body_size_bytes'] = $stats['bulk_body_size_bytes'] + $body_new_size;
        $stats['bulk_body_size'] = $this->admin->humanFileSize($stats['bulk_body_size_bytes']);

        $this->admin->setStats($stats);

        add_filter('ep_intercept_remote_request', [$this, 'interceptTrue']);

        if ($stats['bulk_body_size_bytes'] > $this->settings->payload_min) {
            // prepare interception
            add_filter('ep_do_intercept_request', [$this, 'requestIntercept'], 11, 4);
            return true;
        }

        add_filter('ep_do_intercept_request', [$this, 'requestInterceptFalsy'], 11, 4);
        return true;
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
     * @param array $message_stats
     * @return array|WP_Error
     * @uses add_filter('ep_do_intercept_request');
     *
     * @uses add_filter('ep_intercept_remote_request', true);
     */
    public function requestIntercept($request, $query, $args, $failures, &$message_stats = [])
    {
        $stats = $this->admin->getStats();
        // $body_file = $this->admin->importLocation() . 'moj-bulk-index-body.json';
        // $body = file_get_contents($body_file);
        $body = $this->getBody();
        if (!$body) {
            if ($request === 'doing-cleanup') {
                $this->admin->message('The index body could not be accessed', $message_stats);
            }
        }
        $args['body'] = $body;
        $args['timeout'] = 120; // for all requests

        if ($request === 'doing-cleanup') {
            $this->admin->message('Executing <small><code>wp_remote_request()</code></small>', $message_stats);
        }

        // send payload
        $remote_request = wp_remote_request($query['url'], $args);

        if (is_wp_error($remote_request)) {
            if ($request === 'doing-cleanup') {
                $this->admin->message('There was a transport error: ' . $remote_request->get_error_message(), $message_stats);
                $this->admin->message('About to sleep for 5 and send again in the file; ' . basename(__FILE__), $message_stats);
            }
            sleep(5);
            return $this->requestIntercept($request, $query, $args, $failures, $message_stats);
        }

        if ($request === 'doing-cleanup') {
            $this->admin->message('Last index body has been sent!', $message_stats);
        }

        // unlink($body_file);
        $this->clearBody();

        if ($request === 'doing-cleanup') {
            $this->admin->message('Index body file has been emptied ready for next run.', $message_stats);
        }

        $stats['bulk_body_size'] = 0;
        $stats['total_bulk_requests'] = $stats['total_bulk_requests'] ?? 0;
        $stats['total_bulk_requests']++;
        $this->admin->setStats($stats); // next, save request params

        if ($request === 'doing-cleanup') {
            $this->admin->message('Returning to the cleanup method...', $message_stats);
        }

        return $remote_request;
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
        $stats = $this->admin->getStats();
        $stats['total_stored_requests'] = $stats['total_stored_requests'] ?? 0;
        $stats['total_stored_requests']++;

        // remove body from overall payload for reporting
        unset($args['body']);
        $args['timeout'] = 120; // for all requests
        $stats['last_url'] = $query['url'];
        $stats['last_args'] = $args;

        $this->admin->setStats($stats);

        // mock response
        $request = array(
            'headers' => array(),
            'body' => '{"took":8,"ingest_took":0,"errors":false,"items":[{"index":{"_index":"my-index","_type":"_doc","_id":"12345","_version":9,"result":"updated","_shards":{"total":2,"successful":2,"failed":0},"_seq_no":9,"_primary_term":1,"status":200}}]}',
            'response' => array(
                'code' => 200,
                'message' => 'OK (falsy)',
            ),
            'cookies' => array(),
            'http_response' => [],
        );

        return $request;
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
            return 'Still indexing :(';
        }

        $stats = $this->admin->getStats();
        $this->admin->messageReset($stats);

        // process lock sanity check
        if ($stats['cleanup_loops'] > 2) {
            if (get_option('moj_es_cleanup_process_running')) {
                $this->admin->message('Cleanup is stuck. Deleting the cleanup process lock and trying again.', $stats);
                delete_option('moj_es_cleanup_process_running');
                $stats['cleanup_loops'] = 0;
            }
        }

        // bail if process has started
        if (get_option('moj_es_cleanup_process_running')) {
            $stats['cleanup_loops']++;
            return false;
        }


        if ($stats['cleanup_loops'] > 3) {
            $this->admin->message('Cleanup is stuck. Quitting now to prevent continuous loops...', $stats);
            $this->endCleanup($stats);
        }

        // $file_location = $this->admin->importLocation() . 'moj-bulk-index-body.json';
        $body_text = $this->getBody();
        $this->admin->message('CHECK: Getting last index body from _moj_es_bulk_index_body', $stats);

        // clearstatcache(true, $body_location);
        // if (file_exists($body_location)) {
        // $this->admin->message('CHECK: The index body file exists', $stats);
        // $file_size = filesize($body_location);

        $body_text_size = mb_strlen($body_text, 'UTF-8');

        $this->admin->message('CHECK: The body size is ' . $this->admin->humanFileSize($body_text_size), $stats);

        if ($body_text_size > 0) {
            // begin cleaning
            $cleanup_start = time();
            update_option('moj_es_cleanup_process_running', $cleanup_start);
            $this->admin->message(
                '<strong style="color: #008000">Checks have PASSED.</strong> Cleaning started at <strong>' . date('H:i:s (d:m:y)', $cleanup_start) . '</strong>',
                $stats
            );

            // send last batch of posts to index
            $url = $stats['last_url'] ?? null;
            $args = $stats['last_args'] ?? null;

            if (!$url || !$args) {
                $message = 'Cleanup cannot run. URL or ARGS not available in stats array.';
                $this->admin->message($message, $stats);
                trigger_error($message);
            }

            $this->admin->message('We have URL and ARGs. Moving to send the last request...', $stats);

            // local function ensure object is array
            $toArray = function ($x) use (&$toArray) {
                return is_scalar($x) ? $x : array_map($toArray, (array)$x);
            };

            $response = $this->requestIntercept('doing-cleanup', ['url' => $url], $toArray($args), null, $stats);
            if (is_wp_error($response)) {
                trigger_error($response->get_error_message(), E_USER_ERROR);
            }

            $this->admin->message(
                'Done, the request was sent successfully to ES... attempting to begin polling for completion.',
                $stats
            );

            // Poll for completion
            try {
                $this->alias->pollForCompletion($stats);
            } catch (Exception $e) {
                trigger_error('Exception: Could not initialise pollForCompletion. ' . $e->getMessage());
                $this->admin->message(
                    'Exception: Could not initialise pollForCompletion. ' . $e->getMessage(),
                    $stats
                );
            }

            $this->endCleanup($stats);
            return true;
        }
        // }

        $this->admin->message('The index body file DOES NOT exist', $stats);
        $this->admin->message('Clean up process DID NOT successfully complete on this occasion.', $stats);
        $stats['cleanup_loops']++;
        $this->admin->setStats($stats);

        return null;
    }

    public function endCleanup(&$stats)
    {
        $this->admin->message('<strong style="color: #008000">Starting Housekeeping</strong>', $stats);
        // now we are done, stop the cron hook from running:
        $timestamp = wp_next_scheduled('moj_es_cleanup_cron');
        wp_unschedule_event($timestamp, 'moj_es_cleanup_cron');

        if (false == wp_next_scheduled('moj_es_cleanup_cron')) {
            $this->admin->message('Clean up CRON (moj_es_cleanup_cron) has been removed', $stats);
        } else {
            $this->admin->message('Clean up CRON (moj_es_cleanup_cron) is still running', $stats);
        }

        // quitting the process
        delete_option('moj_es_cleanup_process_running');
        $this->admin->message('Removed the CRON process lock option: moj_es_cleanup_process_running', $stats);

        // set active to false
        delete_option('_moj_es_index_total_items');
        $this->admin->message('Removed the TOTAL ITEMS option: _moj_es_index_total_items', $stats);

        // deleted the bulk body option
        delete_option('_moj_es_bulk_index_body');
        $this->admin->message('Removed the BULK INDEX BODY option: _moj_es_bulk_index_body', $stats);

        // remove the force stop transient
        delete_transient('moj_es_index_force_stopped');
        $this->admin->message('Removed the FORCED STOPPED transient: moj_es_index_force_stopped', $stats);

        // stop timer
        $this->admin->indexTimer(false);
        $this->admin->message('The index timer has been stopped', $stats);

        if (false == ($this->admin->getStats()['force_stop'] ?? false)) {
            // cache the stats for alias updating
            update_option('_moj_es_stats_cache_for_alias_update', $stats);
        }
        $this->admin->setStats($stats);
    }

    /**
     * Manual clean of index process
     */
    public function cleanupIndexingCheck()
    {
        $options = $this->admin->options();
        $stats = $this->admin->getStats();
        $force_clean_up = $options['force_cleanup'] ?? null;

        if ($force_clean_up) {
            $this->admin->messageReset($stats);
            $this->admin->message('Force cleanup -> can we clean? ' . ($force_clean_up ? 'YES' : 'NO'), $stats);
            $this->admin->message('Force cleanup -> WE CLEANED: ' . $this->cleanUpIndexing(), $stats);
            $this->admin->updateOption('force_cleanup', null);
        }

        $this->admin->setStats($stats);
    }

    public function getStatsHTML()
    {
        echo json_encode($this->settings->indexStatisticsAjax());
        exit;
    }

    public function payloadSizes()
    {
        $options = $this->admin->options();
        $payload = $options['max_payload'] ?? $this->settings->payload_ep_max;
        $payload_size = $options['max_payload_size'] ?? 'B';

        $ep_max = round((98 / 100) * $this->admin->human2Byte($payload . $payload_size));
        $max = round((80 / 100) * $ep_max);

        // 25MB in bytes (maximum file build)
        $max_25 = $this->admin->human2Byte('30MB');
        $max = ($max > $max_25 ? $max_25 : $max);

        $payload_sizes = [
            'es_max' => $ep_max,
            'max' => $max,
            'min' => round((80 / 100) * $max)
        ];

        /**
         * If using AWS Lambda - acknowledge quotas:
         * - max payload synchronous = 6MB
         * - max payload asynchronous = 256KB
         */
        if ($this->index_storage === 'lambda') {
            $payload_sizes['max_lambda'] = $this->admin->human2Byte('6MB');
            $payload_sizes['max_lambda_async'] = $this->admin->human2Byte('256KB');
        }

        // return size in bytes format
        return $payload_sizes;
    }

    /**
     * Append a string to the end of a file, or false on failure
     * @param string $body
     * @return false|int
     */
    public function writeBodyToFile(string $body)
    {
        $path = $this->admin->importLocation() . 'moj-bulk-index-body.json';
        $handle = fopen($path, 'a');
        if ($handle !== false) {
            fwrite($handle, trim($body) . "\n");
            while (is_resource($handle)) {
                fclose($handle);
            }

            return true;
        }
        return false;
    }

    private function getBodyFromFile()
    {
        return file_get_contents($this->admin->importLocation() . 'moj-bulk-index-body.json');
    }

    /**
     * Append a string to the end of a string in memory, or false on failure
     * @param string $body
     * @return false|int
     */
    public function writeBodyToMemory(string $body)
    {
        if (!$this->bulk_index_body) {
            $this->bulk_index_body = fopen("php://memory", "r+");
        }

        return fputs($this->bulk_index_body, trim($body) . "\n");
    }

    private function getBodyFromMemory()
    {
        if (!$this->bulk_index_body) {
            $this->bulk_index_body = fopen("php://memory", "r+");
            return '';
        }

        rewind($this->bulk_index_body);
        return stream_get_contents($this->bulk_index_body);
    }

    private function clearBodyMemory()
    {
        fclose($this->bulk_index_body);
    }

    /**
     * Append a string to the end of a file in S3, or false on failure
     * @param string $body
     * @param bool $async
     * @return void
     */
    public function writeBodyToS3(string $body, bool $async)
    {
        $bash_script = true;

        $payload = new \stdClass();
        $payload->filename = "bulk-body"; // no file extension
        $payload->folder = "moj-es";
        $payload->data = trim($body, "\n");
        $payload->production = false;

        $lambda_function = 'intranet-write-es-to-s3';
        $payload = addslashes(json_encode($payload));

        // async has a limited payload size
        $async_cmd = ($bash_script ? '0' : 'RequestResponse');
        if ($async) {
            $async_cmd = ($bash_script ? '1' : 'Event');
        }

        // checks how many running aws processes there are
        // will sleep for nth seconds to allow background processes to complete
        // frees up the OS to perform other tasks
        if ((int)`pgrep aws | wc -l` > 30) {
            sleep(3);
        }

        // the cli request
        /*`aws lambda invoke \
            --cli-binary-format raw-in-base64-out \
            --function-name {$lambda_function} \
            --payload "{$payload}" \
            --invocation-type {$async_cmd}\
            store-data-size.json > /dev/null 2>&1 & echo $!;`;*/

        // the SDK request <- less problematic
        exec(
            MOJ_ES_DIR . '/bin/store-data.sh "' .
            $lambda_function . '" "' .
            $payload . '" ' .
            $async_cmd . ' > /dev/null 2>&1 & echo $!;'
        );

        // the throttle
        usleep(80000);
    }

    /**
     * @return string
     */
    private function getBodyFromS3()
    {
        // TODO: there is another lambda function that gets all the files and consolidates in to one = bulk-body.json
        try {
            // Get the bulk file for sending to ES.
            $result = $this->admin->s3->getObject([
                'Bucket' => $this->admin->getS3Bucket(),
                'Key' => $this->admin->getS3Key()
            ]);

            return $result['Body'];
        } catch (S3Exception $e) {
            trigger_error($e->getMessage() . PHP_EOL);
        }

        return '';
    }

    private function clearBodyS3()
    {
        // TODO: delete the file from s3
    }

    private function writeBody($body, $async = false)
    {
        if ($this->index_storage === 'lambda') {
            $this->writeBodyToS3($body, $async);
        }

        if ($this->index_storage === 'memory') {
            $this->writeBodyToMemory($body);
        }

        return $this->writeBodyToFile($body);
    }

    private function getBody()
    {
        $process_storage = $this->admin->options()['process_storage'] ?? null;
        if ($process_storage === 'lambda') {
            return $this->getBodyFromS3();
        }

        if ($process_storage === 'memory') {
            return $this->getBodyFromMemory();
        }

        if ($process_storage === 'file') {
            return $this->getBodyFromFile();
        }

        return false;
    }

    private function clearBody()
    {
        $process_storage = $this->admin->options()['process_storage'] ?? null;
        if ($process_storage === 'lambda') {
            return $this->clearBodyS3();
        }

        if ($process_storage === 'memory') {
            $this->clearBodyMemory();
        }

        if ($process_storage === 'file') {
            $filepath = $this->admin->importLocation() . 'moj-bulk-index-body.json';
            clearstatcache(true, $filepath);
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}
