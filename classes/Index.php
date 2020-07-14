<?php

namespace MOJElasticSearch;

use function ElasticPress\Utils\is_indexing;

/**
 * Class Index
 * @package MOJElasticSearch
 * @SuppressWarnings("unused")
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
class Index extends Admin
{
    /**
     * This class requires settings fields in the plugins dashboard.
     * Include the Settings trait
     */
    use Settings, Debug;

    private $stat_output_echo = true;

    public function __construct()
    {
        parent::__construct();
        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_menu', [$this, 'pageSettings'], 1);
        add_filter('ep_pre_request_url', [$this, 'request'], 11, 5);
        add_action('moj_es_cron_hook', [$this, 'cleanUpIndexing']);
        add_action('wp_ajax_stats_load', [$this, 'getStatsHTML']);
        add_action('http_api_debug', [$this, 'showResponse'], 10, 5);
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

        // add body to bodies if not too big
        $this->writeBodyToFile($body);

        $this->setStats($stats);


        if ($body_stored_size + $body_new_size > $this->payload_min) {
            // prepare interception
            add_filter('ep_intercept_remote_request', [$this, 'interceptTrue']);
            add_filter('ep_do_intercept_request', [$this, 'requestIntercept'], 11, 4);
            return true;
        }

        add_filter('ep_intercept_remote_request', [$this, 'interceptTrue']);
        add_filter('ep_do_intercept_request', [$this, 'requestInterceptFalsy'], 11, 4);
        return true;
    }

    public function showResponse($response, $response_name, $requests_name, $parsed_args, $url)
    {
        if (str_replace('intranet.local', '', $url) !== $url) {
            update_option('_moj_es_index_debug', $this->debug('Response', $response));
        }
    }

    public function writeBodyToFile($body)
    {
        $path = $this->importLocation() . 'moj-bulk-index-body.json';
        $handle = fopen($path, 'a');
        fwrite($handle, trim($body) . "\n");
        fclose($handle);

        return mb_strlen(file_get_contents($path));
    }

    public function requestIntercept($request, $query, $args, $failures)
    {
        $stats = $this->getStats();

        $args['body'] = file_get_contents($this->importLocation() . 'moj-bulk-index-body.json') . "\n";
        $args['timeout'] = 120; // for all requests

        // this doesn't run (anymore!) when the cron kicks it off.
        //  - what has changed?
        //  - Data is available to make the request - true
        //  -
        $request = wp_remote_request($query['url'], $args);

        $stats['total_bulk_requests'] = $stats['total_bulk_requests'] ?? 0;
        $stats['total_bulk_requests']++;
        $stats['bulk_body_size'] = 0;
        $stats['last_url'] = $query['url'];
        $stats['status_code'] = wp_remote_retrieve_response_code($request);
        $stats['message'] = wp_remote_retrieve_response_message($request);
        $this->setStats($stats);

        unlink($this->importLocation() . 'moj-bulk-index-body.json');

        return $request;
    }

    public function requestInterceptFalsy($request, $query, $args, $failures)
    {
        $stats = $this->getStats();
        $stats['total_stored_requests'] = $stats['total_stored_requests'] ?? 0;
        $stats['total_stored_requests']++;
        $stats['last_url'] = $query['url'];

        // remove body from overall payload for reporting
        unset($args['body']);
        $args['timeout'] = 120; // for all requests
        $stats['last_args'] = json_encode($args);
        $this->setStats($stats);

        // mock response
        return array(
            'headers' => array(),
            'body' => file_get_contents(__DIR__ . '/../assets/mock.json'),
            'response' => array(
                'code' => 200,
                'message' => 'OK',
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
     * @return bool
     */
    public function cleanUpIndexing()
    {
        if (is_indexing()) {
            return false;
        }

        $file_location = $this->importLocation() . 'moj-bulk-index-body.json';

        if (file_exists($file_location)) {
            if (filesize($file_location) > 0) {
                // send last batch of posts to index
                $stats = $this->getStats();
                $url = $stats['last_url'] ?? null;
                $args = $stats['last_args'] ?? null;
                $args = (array)json_decode($args);
                $stat_error = false;

                if (!$url || !$args) {
                    $stats['cleanup_error'] = 'Cleanup cannot run. URL or ARGS not available in stats array';
                    $stat_error = true;
                }

                $this->requestIntercept(null, ['url' => $url], $args, null);

                if ($stat_error) {
                    $this->setStats($stats);

                    wp_mail(
                        get_bloginfo('admin_email'),
                        'Search indexing errors have been found in ' . get_bloginfo('name'),
                        $this->debug('Indexing Stats', $stats)
                    );
                }

                // now we are done, stop the cron hook from running:
                $timestamp = wp_next_scheduled('moj_es_cron_hook');
                wp_unschedule_event($timestamp, 'moj_es_cron_hook');

                return true;
            }
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
            'refresh_index' => [$this, 'indexButton']
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
        if (is_indexing()) {
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
        }/* else {
            $output .= get_option('_moj_es_index_debug');
        }*/

        $output .= '<ul id="inner-indexing-stats"><div style="display:none">' . $this->rand(500) . '</div>';
        $total_files = $requests = '';

        foreach ($this->getStats() as $key => $stat) {
            if (strpos($key, 'last_') > -1) {
                //continue;
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
}
