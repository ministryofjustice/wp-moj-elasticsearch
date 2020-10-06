<?php

namespace MOJElasticSearch;

use MOJElasticSearch\Alias as Alias;
use WP_Error;
use function ElasticPress\Utils\is_indexing;

/**
 * Class Index
 * @package MOJElasticSearch
 * @SuppressWarnings(PHPMD)
 */
class Index extends Admin
{
    /**
     * This class requires settings fields in the plugins dashboard.
     * Include the Settings trait
     */
    use Settings, Debug;

    private $stat_output_echo = true;

    /**
     * The minimum payload size we create before sending to ES
     * @var int size in bytes
     */
    public $payload_min = 53500000;

    /**
     * The maximum we allow for a custom created payload file
     * @var int size in bytes
     */
    public $payload_max = 55000000;

    /**
     * The absolute maximum for any single payload request
     * @var int size in bytes
     */
    public $payload_ep_max = 98000000;

    /**
     * Cache the name of a bulk index route
     * @var int size in bytes
     */
    public $index_name_current = '';

    /**
     * Cache the name of a bulk index route
     * @var int size in bytes
     */
    public $index_name_new = null;

    private $alias = null;
    private $alias_first_run = false;

    public function __construct()
    {
        parent::__construct();

        // construct alias
        $this->alias = new Alias();

        // smaller server on dev
        if ($this->env === 'development') {
            $this->payload_min = 6000000;
            $this->payload_max = 8900000;
            $this->payload_ep_max = 9900000;
        }

        $this->index_name_current = get_option('_moj_es_index_name', null);

        if (!$this->index_name_current) {
            $this->index_name_current = $this->getIndexName();
            update_option('_moj_es_index_name', $this->index_name_current);

            $this->alias_first_run = true;
            $this->alias->add($this->index_name_current);
        }

        self::hooks();
    }

    /**
     * A place for all class specific hooks and filters
     */
    public function hooks()
    {
        add_action('admin_menu', [$this, 'pageSettings'], 1);
        add_filter('ep_pre_request_url', [$this, 'request'], 11, 5);
        add_action('moj_es_cron_hook', [$this, 'cleanUpIndexing']);
        add_action('plugins_loaded', [$this, 'cleanUpIndexingCheck']);
        add_action('wp_ajax_stats_load', [$this, 'getStatsHTML']);
        add_filter('ep_index_name', [$this, 'indexNames'], 11, 1);
        add_filter('ep_is_indexing', [$this, 'returnFalse'], 10, 1);
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
        // reset intercept
        remove_filter('ep_intercept_remote_request', [$this, 'interceptTrue']);
        remove_filter('ep_do_intercept_request', [$this, 'requestIntercept'], 11);
        remove_filter('ep_do_intercept_request', [$this, 'requestInterceptFalsy'], 11);

        // schedule a task to clean up the index once it has finished
        if (!wp_next_scheduled('moj_es_cron_hook')) {
            wp_schedule_event(time(), 'one_minute', 'moj_es_cron_hook');
        }

        $allow_methods = [
            'POST',
            'PUT'
        ];

        $disallow_paths = [
            '/_search',
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
            remove_filter('ep_is_indexing', [$this, 'returnFalse'], 10);
            $request = $this->index($request, $failures, $host, $path, $args);
            add_filter('ep_is_indexing', [$this, 'returnFalse'], 10, 1);
        }

        return $request;
    }

    /**
     *
     * @param string $alias_name
     * @return string
     */
    public function indexNames(string $alias_name): string
    {
        if (!$this->isIndexing()) {
            return $alias_name;
        }

        return $this->getIndexName();
    }

    private function getIndexName()
    {
        if ($this->isIndexing()) {
            return $this->index_name_current;
        }

        if ($this->index_name_new) {
            return $this->index_name_new;
        }

        // index names
        $index_names = [
            'mythical' => [
                'afanc', 'alphyn', 'amphiptere', 'basilisk', 'bonnacon', 'cockatrice', 'crocotta', 'dragon', 'griffin',
                'hippogriff', 'mandragora', 'manticore', 'melusine', 'ouroboros', 'salamander', 'woodwose'
            ]
        ];

        $new_index_names = array_rand($index_names); // string
        $new_index_key = array_rand($index_names[$new_index_names]); // int
        $new_index = $index_names[$new_index_names][$new_index_key];

        if ($new_index === $this->index_name_current) {
            $new_index = $this->getIndexName();
        }

        $new_index = $this->alias->name . "." . $new_index;

        if (!$this->index_name_current) {
            $this->index_name_current = $new_index;
        }

        update_option('_moj_es_new_index_name', $new_index);
        $this->index_name_new = $new_index;

        echo $this->debug('CURRENT INDEX NAME', $this->index_name_current);
        echo $this->debug('NEW INDEX NAME', $new_index);

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
        if (mb_strlen($args['body'], 'UTF-8') <= $this->payload_ep_max) {
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

        $stats['bulk_body_size'] = $this->humanFileSize($body_stored_size);

        // payload maybe too big?
        if ($body_stored_size + $body_new_size > $this->payload_max) {
            return false; // index individual file (normal)
        }

        // add body to bodies
        $this->writeBodyToFile($body);

        $this->setStats($stats);

        add_filter('ep_intercept_remote_request', [$this, 'interceptTrue']);

        if ($body_stored_size + $body_new_size > $this->payload_min) {
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
            'body' => file_get_contents(__DIR__ . '/../assets/mock.json'),
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
     * Method needed to remove_filter
     * @return bool
     */
    public function returnFalse()
    {
        return false;
    }

    /**
     * Makes sure there's no data left in the bulk file once indexing has completed
     * @return bool
     */
    public function cleanUpIndexing()
    {
        $result = $this->alias->add('intranet.local.woodwose');
        if (is_wp_error($result)) {
            $this->debug('WP ERROR', $result);
        }

        if ($this->isIndexing()) {
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

                // local function to convert object to array
                $toArray = function ($x) use (&$toArray) {
                    return is_scalar($x) ? $x : array_map($toArray, (array)$x);
                };

                $this->requestIntercept(null, ['url' => $url], $toArray($args), null);

                // update where the alias points to
                $this->alias->update($this->index_name_new, $this->index_name_current);

                // now we are done, stop the cron hook from running:
                $timestamp = wp_next_scheduled('moj_es_cron_hook');
                wp_unschedule_event($timestamp, 'moj_es_cron_hook');

                return true;
            }
        }

        return false;
    }

    public function cleanUpIndexingCheck()
    {
        $force_clean_up = $this->options()['force_clean_up'] ?? null;
        if ($force_clean_up) {
            $this->updateOption('force_clean_up', null);
            $this->cleanUpIndexing();
        }
    }

    /**
     * This method is quite literally a space saving settings method
     *
     * Create your tab by adding to the $tabs global array with a label as the value
     * Configure a section with fields for that tab as arrays by adding to the $sections global array.
     *
     * @SuppressWarnings(PHPMD)
     */
    public function pageSettings()
    {
        // define section (group) and tabs
        $group = 'indexing';
        Admin::$tabs[$group] = 'Indexing';

        // define fields
        $fields_index = [
            'storage_is_db' => [$this, 'storageIsDB'],
            'polling_delay' => [$this, 'pollingDelayField'],
            'latest_stats' => [$this, 'indexStatistics'],
            'refresh_index' => [$this, 'indexButton'],
            'force_clean_up' => [$this, 'forceCleanUp']
        ];

        // fill the sections
        Admin::$sections[$group] = [
            $this->section([$this, 'indexStatisticsIntro'], $fields_index)
        ];

        $this->createSections($group);
    }

    public function indexStatistics()
    {
        echo '<div id="moj-es-indexing-stats">
                <div class="loadingio-spinner-spinner-qniwaf77spg">
                <div class="ldio-hokc2a6hk1r">
                <div></div><div></div><div></div><div></div><div></div><div></div>
                <div></div><div></div><div></div><div></div><div></div><div></div>
                </div></div></div>
                <div id="my-kill-content-id" style="display:none;">
                        <p>
                            You are about to kill a running indexing process. If you are unsure, exit
                            out of this box by clicking away from this modal.<br><strong>Please confirm:</strong>
                        </p>
                        <a class="button-primary kill_index_pre_link" title="Are you sure?">
                            Yes, let\'s kill this... GO!
                        </a>
                    </div>';
    }

    private function maybeBulkBodyFormat($key)
    {
        return $key === 'bulk_body_size' ? ' / ' . $this->humanFileSize($this->payload_min) : '';
    }

    public function indexStatisticsIntro()
    {
        $heading = __('View the results from the latest index', $this->text_domain);

        $description = __('', $this->text_domain);
        echo '<div class="intro"><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function indexButton()
    {
        $description = __(
            'You will be asked to confirm your decision. Please use this button with due consideration.',
            $this->text_domain
        );
        ?>
        <div id="my-content-id" style="display:none;">
            <p>
                Please make sure you are aware of the implications when commanding a new index. If you are unsure, exit
                out of this box by clicking away from this modal.<br><strong>Please confirm:</strong>
            </p>
            <a class="button-primary index_pre_link"
               title="Are you sure?">
                I'm ready to refresh the index... GO!
            </a>
        </div>
        <button name='<?= $this->optionName() ?>[index_button]' class="button-primary index_button" disabled="disabled">
            Destroy index and refresh
        </button>
        <a href="#TB_inline?&width=400&height=150&inlineId=my-content-id" class="button-primary thickbox"
           title="Refresh Elasticsearch Index">
            Destroy index and refresh
        </a>
        <p><?= $description ?></p>
        <?php
    }

    public function storageIsDB()
    {
        $option = $this->options();
        $storage_is_db = $option['storage_is_db'] ?? null;
        ?>
        <p>Should we store index stats in the DB or write them to disc?</p>
        <input
            type="checkbox"
            value="1"
            name="<?= $this->optionName() ?>[storage_is_db]"
            <?php checked('1', $storage_is_db) ?>
        /> <small id="storage_indicator"><?= ($this->stats_use_db ? 'Yes, store in DB' : 'No, write to disc') ?></small>
        <?php
    }

    public function forceCleanUp()
    {
        $option = $this->options();
        $force_clean_up = $option['force_clean_up'] ?? null;
        ?>
        <p>Do we need to clean the indexing process up? This might be needed if Bulk Body Size is greater than 0</p>
        <input
            type="checkbox"
            value="1"
            name="<?= $this->optionName() ?>[force_clean_up]"
            <?php checked('1', $force_clean_up) ?>
        /> <small id="force_clean_up_indicator"><?= ($force_clean_up ? 'Yes, clean up' : 'No') ?></small>
        <?php
    }

    public function pollingDelayField()
    {
        $option = $this->options();
        $key = 'polling_delay';
        ?>
        <input type="text" value="<?= $option[$key] ?? 3 ?>" name="<?= $this->optionName() ?>[<?= $key ?>]"/>
        <small>Seconds</small>
        <p>This setting affects the amount of time Latest Stats (below) is refreshed.</p>
        <script>
            var mojESPollingTime = <?= $option[$key] ?? 3 ?>
        </script>
        <?php
    }

    public function indexStatisticsAjax()
    {
        $output = '';
        if ($this->isIndexing()) {
            $output .= '<div class="notice notice-warning moj-es-stats-index-notice">
                    <p><strong>Indexing is currently active</strong>
                    <button name="moj_es_settings[index_kill]" value="1" class="button-primary kill_index_button"
                        disabled="disabled">
                        Stop Indexing
                    </button>
                    <a href="#TB_inline?&width=400&height=150&inlineId=my-kill-content-id"
                        class="button-primary kill_index_button thickbox"
                        title="Kill Elasticsearch Indexing">
                        Stop Indexing
                    </a></p>
                </div>';
        }

        $output .= '<ul id="inner-indexing-stats">';
        $total_files = $requests = '';

        foreach ($this->getStats() as $key => $stat) {
            if (strpos($key, 'last_') > -1) {
                continue;
            }

            if ($key === 'large_files') {
                $large_file_count = count($stat);
                $total_files = '<li>Large posts (skipped): we have found <strong>' .
                    $large_file_count . '</strong> large item' . ($large_file_count === 1 ? '' : 's') .
                    '</li>';

                if (!empty($stat)) {
                    $total_files .= '<li>' . ucwords(str_replace('_', ' ', $key)) . ':';
                    $total_files .= '<div class="large_file_holder"><ol>';
                    foreach ($stat as $pid) {
                        $link = '<a href="/wp/wp-admin/post.php?post=' .
                            $pid->index->_id . '&action=edit" target="_blank">' .
                            $pid->index->_id . '</a>';
                        $total_files .= '<li><strong>' . $link . '</strong></li>';
                    }
                    $total_files .= '</ol></div></li>';
                }
            }

            if ($key !== 'large_files') {
                $requests .= '<li>' .
                    ucwords(
                        str_replace(['total', '_'], ['', ' '], $key)
                    ) . ': <strong>' . print_r($stat, true) .
                    '</strong>' . $this->maybeBulkBodyFormat($key) .
                    '</li>';
            }
        }

        return $output . $requests . $total_files . '</ul>';
    }

    public function getStatsHTML()
    {
        $this->options();
        $stats = $this->indexStatisticsAjax();

        echo json_encode($stats);
        die();
    }

    public function isIndexing(): bool
    {
        return get_transient('ep_wpcli_sync');
    }
}
