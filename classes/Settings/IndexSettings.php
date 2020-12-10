<?php

namespace MOJElasticSearch\Settings;

use MOJElasticSearch\Admin;
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

    public function __construct(Admin $admin)
    {
        parent::__construct();

        $this->admin = $admin;
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
            'index_status' => [$this, 'indexStatus'],
            'alias_status' => [$this, 'currentStatus']

        ];

        $fields_index_management = [
            'storage_is_db' => [$this, 'storageIsDB'],
            'build_index' => [$this, 'indexButton'],
            'refresh_rate' => [$this, 'pollingDelayField'],
            'force_wp_query' => [$this, 'forceWPQuery'],
            'force_clean_up' => [$this, 'forceCleanUp']
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
            <?= $this->listAliasIndexes($current_alias) ?>
        </p>
        <?php
    }

    public function listAliasIndexes($current_alias)
    {
        if (!empty($current_alias)) {
            $url = get_option('EP_HOST') . '_cat/aliases/' . $current_alias . '?v&format=json&h=index';
            $response = wp_safe_remote_get($url);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                $alias_indexes = json_decode(wp_remote_retrieve_body($response));

                if (is_array($alias_indexes)) {
                    foreach ($alias_indexes as $alias_index) { ?>
                        <p><?php echo $alias_index->index; ?></p>
                        <?php
                    }
                } else { ?>
                    <p>No Indexes are assigned to the alias</p>
                    <?php
                }
            } else {
                ?>
                <p>Error Connecting to ElasticSearch</p>
                <?php
            }
        } else {
            ?>
            <p>Alias is not set</p>
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
                I'm ready to rebuild the index... GO!
            </a>
        </div>
            <button name='<?= $this->optionName() ?>[index_button]' class="button-primary index_button" disabled="disabled">
                Build new index
            </button>
            <a href="#TB_inline?&width=400&height=150&inlineId=my-content-id" class="button-primary thickbox"
            title="Rebuild Elasticsearch Index">
                Build new index
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

    public function forceWPQuery()
    {
        $option = $this->options();
        $force_wp_query = $option['force_wp_query'] ?? null;
        ?>
        <p>Has something gone wrong with Elasticsearch? Check this option to allow ElasticPress to use WP Query</p>
        <input
            type="checkbox"
            value="1"
            name="<?= $this->optionName() ?>[force_wp_query]"
            <?php checked('1', $force_wp_query) ?>
        /> <small
        id="force_wp_query_indicator"><?= ($force_wp_query ? 'Yes, force WP Query while indexing' : 'No') ?></small>

        <small style="font-size: 0.85rem"><br>
            <br>Although very rare, we may need to override the default behaviour of querying by alias. <br>
            Out of the box, we prevent ElasticPress from using WP Query on front end searches while<br>
            indexing. Selecting this option will allow ElasticPress to fallback to WP Query.<br>
            Useful in the event of catastrophic failure, such as an index being removed or emptied by accident.
        </small>
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

    public function indexStatisticsAjax()
    {
        $output = '';
        $index_stats = $this->admin->isIndexing(true);
        $total_items = $index_stats[1] ?? 0;
        // store the total items
        if (! get_option('_moj_es_index_total_items', false) && $total_items > 0) {
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

            $index_active = get_option('_moj_es_bulk_index_active');
            // clean up variables
            $clean_up_status = wp_next_scheduled('moj_es_cleanup_cron');
            $clean_up_text_completed = 'Cleaned';
            $clean_up_text = ($clean_up_status ? 'Cleaning up' : $clean_up_text_completed);

            // alias switch variables
            $alias_switch_text = 'Cancelled';
            $alias_class = ' cancelled';
            if ($this->admin->allItemsIndexed()) {
                $alias_switch_status = wp_next_scheduled('moj_es_poll_for_completion');
                $alias_switch_text = ($alias_switch_status ? 'Switching' : 'Waiting...');
                $alias_switch_text = (!$index_active ? 'Updated' : $alias_switch_text);
                $alias_class = ($alias_switch_status ? ' active' : '');
            }

            $output .= '<div class="index-complete-status-blocks">
                            <div class="status-box clean-up
                                ' . ($clean_up_text == $clean_up_text_completed ? ' complete' : '') . '
                                ' . ($clean_up_status ? ' active' : '') . '
                                ">
                                <small><em>Index</em></small><br>
                                <span>' . $clean_up_text . '</span>
                                <div class="loader"></div>
                            </div>
                            <div class="status-box alias-switch
                                ' . (!$index_active ? ' complete' : $alias_class) . '
                                ">
                                <small><em>Alias</em></small><br>
                                <span>' . $alias_switch_text . '</span>
                                <div class="loader"></div>
                            </div>
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
                $requests .= '<li class="'.$key.'">' .
                    ucwords(
                        str_replace(['total', '_'], ['', ' '], $key)
                    ) . ' / ' . $total_items . ' <strong>' . print_r($stat, true) .
                    '</strong>' .
                    '</li>';

                continue;
            }

            if ($key !== 'large_files') {
                $requests .= '<li class="'.$key.'">' .
                    ucwords(
                        str_replace(['total', '_'], ['', ' '], $key)
                    ) . ' <strong>' . print_r($stat, true) .
                    '</strong>' . $this->maybeBulkBodyFormat($key) .
                    '</li>';
            }
        }

        return $output . $requests . $total_files . '</ul>';
    }
}
