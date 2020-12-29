<?php

namespace MOJElasticSearch\Settings;

use MOJElasticSearch\Admin;
use MOJElasticSearch\Alias;
use MOJElasticSearch\Settings;

/**
 * Class IndexSettings
 * @package MOJElasticSearch\Settings
 * @SuppressWarnings(PHPMD)
 */
class IndexSettings extends Page
{
    use Settings;

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
     * Admin object
     * @var Admin
     */
    public $admin;

    /**
     * @var Alias
     */
    private $alias;

    public function __construct(Admin $admin, Alias $alias)
    {
        parent::__construct();

        $this->admin = $admin;
        $this->alias = $alias;
        self::hooks();
    }

    public function hooks()
    {
        add_action('admin_menu', [$this, 'pageSettings'], 1);
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
        Page::$tabs[$group] = 'Indexing';

        // define fields
        $fields_index = [
            'latest_stats' => [$this, 'indexStatistics'],
            'build_index' => [$this, 'indexButton'],
            'index_status' => [$this, 'indexStatus'],
            'alias_status' => [$this, 'currentStatus']
        ];

        $fields_index_management = [
            'storage_is_db' => [$this, 'storageIsDB'],
            'max_payload' => [$this, 'maxPayloadSize'],
            'refresh_rate' => [$this, 'pollingDelayField'],
            'force_wp_query' => [$this, 'forceWPQuery'],
            'show_cleanup_messages' => [$this, 'showCleanupMessages'],
            'force_cleanup' => [$this, 'forceCleanup'],
            'buffer_total_requests' => [$this, 'bufferTotalRequests']
        ];

        // fill the sections
        Page::$sections[$group] = [
            $this->section([$this, 'indexStatisticsIntro'], $fields_index),
            $this->section([$this, 'indexManagementIntro'], $fields_index_management)
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
                </div></div>
              </div>
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

    public function indexStatus()
    {
        $last_created_index = get_option('_moj_es_index_name');
        $end_date = get_option('_moj_es_index_timer_stop');
        $end_date = ($end_date
            ? '<br>Created on ' . date("F j, Y, g:i a", $end_date)
            : ''
        );
        ?>

        <p><small><strong>Last completed index</strong>
                <?= $end_date ?>
                <br></small>
            <?= $last_created_index ?>
            <br>
        </p>
        <?php
    }

    public function currentStatus()
    {
        $current_alias = get_option('_moj_es_alias_name');
        $current_alias = $current_alias ? $current_alias : 'No alias found.';
        ?>
        <p><small><strong>Alias</strong></small><br><?= $current_alias ?><br></p>
        <p>-------------</p>
        <p><small><strong>Attached indices</strong></small><br>
            <small>The following index names are attached to the alias (<em><?= $current_alias ?></em>) and
                will produce results when queried.</small>
            <?= $this->listAliasIndexes() ?>
        </p>
        <?php
    }

    public function listAliasIndexes()
    {
        $alias_indexes = $this->alias->getAliasIndexes();

        if ($alias_indexes) {
            if (is_array($alias_indexes)) {
                echo '<br>' . implode('<br>', $alias_indexes);
            } else { ?>
                <p>No Indexes are assigned to the alias</p>
                <?php
            }
        } else {
            ?>
            <p>Error getting the alias indexes.</p>
            <?php
        }
    }

    private function maybeBulkBodyFormat($key)
    {
        return $key === 'bulk_body_size' ? ' / ' . $this->admin->humanFileSize($this->payload_min) : '';
    }

    public function indexStatisticsIntro()
    {
        $heading = __('View the results from the latest index', $this->text_domain);

        $description = __('', $this->text_domain);
        echo '<div class="intro"><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function indexManagementIntro()
    {
        $heading = __('Manage indexing options', $this->text_domain);

        $description = __('', $this->text_domain);
        echo '<div class="intro"><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function indexButton()
    {
        $description = __(
            'You will be asked to confirm your decision. Please build with due consideration.',
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
                I'm ready to rebuild the index... GO!
            </a>
        </div>
        <button name='<?= $this->optionName() ?>[index_button]' class="button-primary index_button" disabled="disabled">
            Build New Index
        </button>
        <a href="#TB_inline?&width=400&height=150&inlineId=my-content-id" class="button-primary thickbox"
           title="Rebuild Elasticsearch Index">
            Build New Index
        </a>
        <p><small><?= $description ?></small></p>
        <?php
    }

    public function storageIsDB()
    {
        $option = $this->options();
        $storage_is_db = $option['storage_is_db'] ?? null;
        ?>
        <input
            type="checkbox"
            value="1"
            name="<?= $this->optionName() ?>[storage_is_db]"
            <?php checked('1', $storage_is_db) ?>
        />
        <small id="storage_indicator"
               class="green"><?= ($this->stats_use_db ? 'Sure, store stats in DB' : 'No, write to disc') ?></small>
        <p>Should we store index stats in the DB or write them to disc?</p>
        <?php
    }

    public function forceCleanup()
    {
        $option = $this->options();
        $force_cleanup = $option['force_cleanup'] ?? null;
        // prompt states
        $prompt_text = 'No';
        $prompt_colour = 'green';
        if ($force_cleanup) {
            $prompt_text = 'Yes, clean up';
            $prompt_colour = 'red';
        }
        ?>
        <input
            type="checkbox"
            value="1"
            name="<?= $this->optionName() ?>[force_cleanup]"
            <?php checked('1', $force_cleanup) ?>
        /> <small id="force_cleanup_indicator" class="<?= $prompt_colour ?>"><?= $prompt_text ?></small>
        <p>Do we need to clean the indexing process up? This might be needed if Bulk Body Size is greater than 0.</p>
        <?php
    }

    public function forceWPQuery()
    {
        $option = $this->options();
        $force_wp_query = $option['force_wp_query'] ?? null;
        // prompt states
        $prompt_text = 'No';
        $prompt_colour = 'green';
        if ($force_wp_query) {
            $prompt_text = 'Yes, clean up';
            $prompt_colour = 'red';
        }
        ?>
        <input
            type="checkbox"
            value="1"
            name="<?= $this->optionName() ?>[force_wp_query]"
            <?php checked('1', $force_wp_query) ?>
        /> <small
        id="force_wp_query_indicator" class="<?= $prompt_colour ?>"><?= $prompt_text ?></small>

        <p>Has something gone wrong with Elasticsearch? Check to allow ElasticPress to use WP Query.</p>
        <p class="feature-desc">
            Although very rare, we may need to override the default behaviour of querying by alias.
            Out of the box, we prevent ElasticPress from using WP Query on front end searches while
            indexing. Selecting this option will allow ElasticPress to fallback to WP Query.
            Useful in the event of catastrophic failure, such as an index being removed or emptied by accident.
        </p>
        <?php
    }

    public function showCleanupMessages()
    {
        $option = $this->options();
        $key = 'show_cleanup_messages';
        $value = $option[$key] ?? null;
        // prompt states
        $prompt_text = 'No';
        $prompt_colour = 'green';
        if ($value) {
            $prompt_text = 'For sure! Display messaging during cleanup';
            $prompt_colour = 'orange';
        }
        ?>
        <input
            type="checkbox"
            value="1"
            name="<?= $this->optionName() ?>[<?= $key ?>]"
            <?php checked('1', $value) ?>
        /> <small
        id="show_cleanup_messages_indicator" class="<?= $prompt_colour ?>"><?= $prompt_text ?></small>
        <p>Show verbose messaging during the clean up process.</p>
        <p class="feature-desc">
            This feature is especially useful to help visualise the steps of the clean up process. Messages will
            only be displayed when the cleanup process kicks off.
        </p>
        <?php
    }

    public function pollingDelayField()
    {
        $option = $this->options();
        $key = 'refresh_rate';
        ?>
        <input type="text" value="<?= $option[$key] ?? 3 ?>" name="<?= $this->optionName() ?>[<?= $key ?>]"/>
        <small>Seconds</small>
        <p>This setting affects the amount of time Latest Stats (above) is refreshed.</p>
        <script>
            var mojESPollingTime = <?= $option[$key] ?? 3 ?>
        </script>
        <?php
    }

    public function bufferTotalRequests()
    {
        $option = $this->options();
        $key = 'buffer_total_requests';
        ?>
        <input type="text" value="<?= $option[$key] ?? 20 ?>" name="<?= $this->optionName() ?>[<?= $key ?>]"/>
        <p>This buffer is necessary and acts as a confidence rating. It helps decide whether a new index should be
            activated and applied to the alias.</p>
        <p class="feature-desc">We check the total number of indexed items against the total available indexables.
            We add this buffer around the total stored requests to account for slight differences.</p>
        <?php
    }

    public function maxPayloadSize()
    {
        $option = $this->options();
        $key = 'max_payload';
        $key_size = 'max_payload_size';
        ?>
        <input type="text" value="<?= $option[$key] ?? 5 ?>" name="<?= $this->optionName() ?>[<?= $key ?>]"/>
        <select name="<?= $this->optionName() ?>[<?= $key_size ?>]">
            <option value="B" <?php selected($option[$key_size], "B"); ?>>Bytes</option>
            <option value="KB" <?php selected($option[$key_size], "KB"); ?>>Kilobytes</option>
            <option value="MB" <?php selected($option[$key_size], "MB"); ?>>Megabytes</option>
            <option value="GB" <?php selected($option[$key_size], "GB"); ?>>Gigabytes</option>
        </select>
        <p>Enter a maximum HTTP request payload limit here. This represents the amount of data that can be sent in one
            request. If using AWS, <a
                href="https://docs.aws.amazon.com/elasticsearch-service/latest/developerguide/aes-limits.html#network-limits"
                target="_blank">view network limits</a>.</p>
        <p class="feature-desc">Data inserts into ES are controlled by analysing a post size. We manage the post
            according to size and effectively build a bulk insert file that can be sent in one request. This approach
            means we don't have to worry about ES rejecting a request due to size. It also means the indexing process
            can continue without interruption.<br><br>Displayed in stats above, you will see a value against the bulk
            body size. This value is a percentage of the max payload amount. Please note that it will not be higher than
            <code>24MB</code>. This is set to improve server load when opening and writing indexables to file.</p>
        <?php
    }

    public function indexStatisticsAjax()
    {
        $output = '';
        $index_stats = $this->admin->isIndexing(true);
        $total_items = $index_stats[1] ?? 0;
        // store the total items
        if (!get_option('_moj_es_index_total_items', false) && $total_items > 0) {
            update_option('_moj_es_index_total_items', $total_items);
        }
        if ($index_stats) {
            $total_sent = $index_stats[0] ?? 0;
            $percent = (($total_sent > 0 && $total_items > 0) ? round(($total_sent / $total_items) * 100) : 0);
            $output .= '<div class="notice notice-warning moj-es-stats-index-notice">
                    <div class="progress">
                        <span style="width: ' . $percent . '%">
                            <span></span>
                        </span>
                        <small>' . get_option('_moj_es_new_index_name') . '</small>
                    </div>
                    <small>' . $percent . '% complete
                        <span style="float:right" id="index-time">' . $this->admin->getIndexedTime() . '</span>
                    </small>
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

        } else {
            $output .= '<span class="index_time">Last index took ' . $this->admin->getIndexedTime() . '</span>';

            $clean_up_progress = $this->feedbackCleanUpProcess();
            $alias_switch_progress = $this->feedbackAliasSwitchProcess($clean_up_progress['state']);

            $output .= '<div class="index-complete-status-blocks">
                            <div class="status-box clean-up ' . $clean_up_progress['state'] . '">
                                <small><em>Index</em></small><br>
                                <span>' . $clean_up_progress['text'] . '</span>
                                <div class="loader"></div>
                            </div>
                            <div class="status-box alias-switch ' . $alias_switch_progress['state'] . '">
                                <small><em>Alias</em></small><br>
                                <span>' . $alias_switch_progress['text'] . '</span>
                                <div class="loader"></div>
                            </div>
                        </div>';
        }

        $output .= '<ul id="inner-indexing-stats">';
        $total_files = $requests = '';

        // define keys to omit from display
        $private_keys = ['last_url', 'last_args', 'force_stop', 'messages', 'cleanup_loops'];
        $stats = $this->admin->getStats();
        foreach ($stats as $key => $stat) {
            if (in_array($key, $private_keys)) {
                continue;
            }

            if ($key === 'large_files') {
                $large_file_count = count($stat);
                $total_files = '<li>Skipped (too large) <strong>' .
                    $large_file_count . '</strong>' .
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
                continue;
            }

            if ($key === 'total_stored_requests') {
                $max_requests_avail = ($total_items > 0 ? ' / ' . $total_items : '');
                $requests .= '<li class="' . $key . '">' .
                    ucwords(
                        str_replace(['total', '_'], ['', ' '], $key)
                    ) . $max_requests_avail . ' <strong>' . print_r($stat, true) .
                    '</strong>' .
                    '</li>';

                continue;
            }

            if ($key !== 'large_files') {
                $requests .= '<li class="' . $key . '">' .
                    ucwords(
                        str_replace(['total', '_'], ['', ' '], $key)
                    ) . ' <strong>' . print_r($stat, true) .
                    '</strong>' . $this->maybeBulkBodyFormat($key) .
                    '</li>';
            }
        }

        return $output . $requests . $total_files . '</ul>' . $this->indexMessages($stats);
    }

    public function feedbackCleanUpProcess()
    {
        $feedback = [
            'text' => 'Cleaned',
            'state' => ''
        ];

        if (wp_next_scheduled('moj_es_cleanup_cron')) {
            $feedback['text'] = 'Cleaning up';
            $feedback['state'] = 'active';
        }

        if ($feedback['text'] === 'Cleaned') {
            $feedback['state'] = 'complete';
        }

        return $feedback;
    }

    public function feedbackAliasSwitchProcess($cleanup_state)
    {
        $feedback = [
            'text' => 'Waiting',
            'state' => ''
        ];

        // waiting
        if ($cleanup_state === 'active') {
            return $feedback;
        }

        // active
        if (wp_next_scheduled('moj_es_poll_for_completion')) {
            $feedback['text'] = 'Switching';
            $feedback['state'] = 'active';
        }

        // complete
        if (!get_option('_moj_es_bulk_index_active')) {
            $feedback['text'] = 'Updated';
            $feedback['state'] = 'complete';
        }

        // cancelled
        if (true === ($this->admin->getStats()['force_stop'] ?? false)) {
            $feedback['text'] = 'Interrupted';
            $feedback['state'] = 'cancelled';
        }

        return $feedback;
    }

    /**
     * @param $stats
     * @return string
     */
    public function indexMessages($stats)
    {
        $output = '';
        if ($this->options()['show_cleanup_messages'] ?? null) {
            $index_messages = $stats['messages'] ?? [];

            if (!empty($index_messages)) {
                $output = '<ol>';
                foreach ($index_messages as $index_message) {
                    $output .= '<li>' . $index_message . '</li>';
                }
                $output .= '</ol>';
            }
        }

        return $output;
    }
}
