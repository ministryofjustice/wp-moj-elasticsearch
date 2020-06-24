<?php

namespace MOJElasticSearch;

/**
 * Class Index
 * @package MOJElasticSearch
 * @SuppressWarnings("unused")
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Index extends Admin
{
    /**
     * This class requires settings fields in the plugins dashboard.
     * Include the Settings trait
     */
    use Settings, Debug;

    public function __construct()
    {
        parent::__construct();
        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_menu', [$this, 'pageSettings'], 1);
        add_filter('ep_pre_request_url', [$this, 'request'], 11, 5);
        add_action('ep_cli_Posts_bulk_index', [$this, 'cleanUpIndexing']);
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

        // check the total size - no more than 9.7Mb
        if (mb_strlen($args['body'], 'UTF-8') <= 9728000) {
            // allow ElasticPress to index normally
            $stats['total_normal_requests']++;
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
     * @param $bodies
     * @param $body
     * @return bool
     */
    public function sendBulk($body)
    {
        $min_byte_size = 5728000;
        $max_byte_size = 8728000;
        $body_new_size = mb_strlen($body, 'UTF-8');
        $body_stored_size = 0;

        if (file_exists($this->importLocation() . 'moj-bulk-index-body.json')) {
            $body_stored_size = filesize($this->importLocation() . 'moj-bulk-index-body.json');
        }

        // payload maybe too big?
        if ($body_stored_size + $body_new_size > $max_byte_size) {
            return false; // index individual file (normal)
        }

        // add body to bodies if not too big
        $this->writeBodyToFile($body);

        if ($body_stored_size + $body_new_size > $min_byte_size) {
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
        update_option('_moj_es_index_debug', $this->debug('Response', $response));
    }

    public function writeBodyToFile($body)
    {
        $path = $this->importLocation() . 'moj-bulk-index-body.json';
        $handle = fopen($path, 'a');
        fwrite($handle, trim($body) . "\n");
        fclose($handle);
    }

    public function requestIntercept($request, $query, $args, $failures)
    {
        $args['body'] = file_get_contents($this->importLocation() . 'moj-bulk-index-body.json');

        $stats = $this->getStats();
        $stats['total_real_requests'] = $stats['total_real_requests'] ?? 0;
        $stats['total_real_requests']++;
        $this->setStats($stats);

        unlink($this->importLocation() . 'moj-bulk-index-body.json');

        return wp_remote_request($query['url'], $args);
    }

    public function requestInterceptFalsy($request, $query, $args, $failures)
    {
        $stats = $this->getStats();
        $stats['total_mock_requests'] = $stats['total_mock_requests'] ?? 0;
        $stats['total_mock_requests']++;
        $stats['last_url'] = $query['url'];

        // remove body from overall payload for reporting
        unset($args['body']);
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

    public function interceptTrue()
    {
        return true;
    }

    public function interceptFalse()
    {
        return false;
    }

    public function cleanUpIndexing()
    {
        // todo: make sure the 'moj-bulk-index-body.json' file isn't empty, if so, one last request and unlink it.
    }

    /**
     * This method is quite literally a space saving settings method
     *
     * Create your tab by adding to the $tabs global array with a label as the value
     * Configure a section with fields for that tab as arrays by adding to the $sections global array.
     */
    public function pageSettings()
    {
        $group = 'indexing';

        Admin::$tabs[$group] = 'Indexing';
        Admin::$sections[$group] = [
            [
                'id' => 'index_stats',
                'title' => 'Latest indexing stats',
                'callback' => [$this, 'indexStatsIntro'],
                'fields' => [
                    'stats' => ['title' => 'Index Statistics', 'callback' => [$this, 'indexStatistics']],
                    'index_button' => ['title' => 'Index Now?', 'callback' => [$this, 'indexButton']]
                ]
            ]
        ];

        $this->createSections($group);
    }

    public function indexStatistics()
    {
        echo '<ul>';
        $total_files = '';
        $requests = '';

        foreach ($this->getStats() as $key => $stat) {
            if ($key === 'large_files') {
                $large_file_count = count($stat);
                $total_files = '<li>Large posts (skipped): we have found <strong>' .
                    $large_file_count . '</strong> large item' . ($large_file_count === 1 ? '' : 's') .
                    '</li>';

                if (!empty($stat)) {
                    $total_files .= '<li>' . ucwords(str_replace('_', ' ', $key)) . ':';
                    $total_files .= '<ol>';

                    foreach ($stat as $pid) {
                        $link = '<a href="/wp/wp-admin/post.php?post=' . $pid . '&action=edit">' . $pid . '</a>';
                        $total_files .= '<li><strong>' . $link . '</strong></li>';
                    }

                    $total_files .= '</ol></li>';
                }
            }

            if ($key !== 'large_files') {
                $requests .= '<li>' .
                    ucwords(str_replace(['total', '_'], ['', ' '], $key)) . ': <strong>' . $stat . '</strong></li>';
            }
        }

        echo $requests;
        echo $total_files;
        echo '</ul>';
    }

    public function indexStatsIntro()
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
}
