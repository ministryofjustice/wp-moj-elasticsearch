<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

use function ElasticPress\Utils\is_indexing;

/**
 * Class Admin
 * @package MOJElasticSearch
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Admin
{
    use Debug;

    public $prefix = 'moj_es';
    public $option_name = '_settings';
    public $option_group = '_plugin';
    public $text_domain = 'wp-moj-elasticsearch';
    public static $tabs = [];
    public static $sections = [];
    public $settings_registered = false;

    /**
     * The minimum payload size we create before sending to ES
     * @var int size in bytes
     */
    public $payload_min = 20000000;
    /**
     * The maximum we allow for a custom created payload file
     * @var int size in bytes
     */
    public $payload_max = 25000000;
    /**
     * The absolute maximum for any single payload request
     * @var int size in bytes
     */
    public $payload_ep_max = 98000000;

    public function __construct()
    {
        $env = env('WP_ENV') ?: 'production';
        if ($env === 'development') {
            $this->payload_min = 6000000;
            $this->payload_max = 8900000;
            $this->payload_ep_max = 9900000;
        }

        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_menu', [$this, 'settingsPage']);
        add_action('ep_dashboard_start_index', [$this, 'clearStats']);
        add_action('ep_wp_cli_pre_index', [$this, 'clearStats']);
        add_filter('cron_schedules', [$this, 'addCronIntervals']);
        add_action('admin_init', [$this, 'register']);
    }

    public function enqueue()
    {
        wp_enqueue_style('moj-es', plugins_url('../', __FILE__) . 'assets/css/main.css', []);
        wp_enqueue_script(
            'moj-es-js',
            plugins_url('../', __FILE__) . 'assets/js/main.js',
            ['jquery']
        );
        wp_localize_script('moj-es-js', 'moj_js_object', array('ajaxurl' => admin_url('admin-ajax.php')));
    }

    /**
     * Registers a setting when createSections() is called first time
     * The register call is singular for the whole plugin
     */
    public function register()
    {
        if (!$this->settings_registered) {
            register_setting(
                $this->optionGroup(),
                $this->optionName(),
                ['sanitize_callback' => [$this, 'sanitizeSettings']]
            );
            $this->settings_registered = true;
        }
    }

    public function settingsPage()
    {
        add_options_page(
            'MoJ ES',
            'MoJ ES',
            'manage_options',
            'moj-es',
            [$this, 'init']
        );
    }

    /**
     * Set up the admin plugin page
     */
    public function init()
    {
        add_thickbox();

        echo '<form action="options.php" method="post" class="moj-es" enctype="multipart/form-data">';

        // Title section
        $title = __('MoJ ES', $this->text_domain);
        $title_admin = __('Extending the functionality of the ElasticPress plugin', $this->text_domain);
        echo '<h1>' . $title . ' <small class="sub-title">.' . $title_admin . '</small></h1>';

        settings_errors();

        // output tab buttons
        $this->tabs();

        // drop sections
        settings_fields($this->optionGroup());
        $this->sections();

        // drop button; update all text, check and process uploads, if required.
        submit_button('Update Settings');

        echo '</form>';
    }

    /**
     * Generates page tabs for each registered module.
     * Uses the $tabs array ~ defined by modules using sections and fields.
     */
    private function tabs()
    {
        echo '<div class="nav-tab-wrapper">';
        foreach (self::$tabs as $tab => $label) {
            echo '<a href="#moj-es-' . $tab . '" class="nav-tab">' . $label . '</a>';
        }
        echo '</div>';
    }

    /**
     * Creates the Dashboard front-end section view in our settings page.
     * Uses the $sections configuration array
     */
    private function sections()
    {
        foreach (self::$sections as $section_group_id => $sections) {
            echo '<div id="moj-es-' . $section_group_id . '" class="moj-es-settings-group">';
            foreach ($sections as $section) {
                echo '<div id="moj-es-' . $section_group_id . '" class="moj-es-settings-section">';
                echo "<h2>" .  ($section['title'] ?? '') . "</h2>\n";

                if ($section['callback']) {
                    call_user_func($section['callback'], $section);
                }

                echo '<table class="form-table" role="presentation">';
                do_settings_fields($this->optionGroup(), $this->prefix . '_' . $section['id']);
                echo '</table>';

                echo '</div>';
            }
            echo '</div>';
        }
        echo '<hr/>';
    }

    /**
     * @param $section_callback array callback in array format [$this, 'mySectionIntroCallback']
     * @param $fields array of callbacks in array format with keys ['my_field_title' => [$this, 'myFieldCallback']]
     * @return array
     */
    public function section($section_callback, $fields): array
    {
        $structured_fields = [];
        foreach ($fields as $field_id => $field_callback) {
            $structured_fields[$field_id] = [
                'title' => ucwords(str_replace(['-', '_'], ' ', $field_id)),
                'callback' => $field_callback
            ];
        }

        return [
            'id' => strtolower($section_callback[1]),
            'title' => ucwords(str_replace('Intro', '', $this->camelCaseToWords($section_callback[1]))),
            'callback' => $section_callback,
            'fields' => $structured_fields
        ];
    }

    /**
     * Intercepts when saving settings so we can sanitize, validate or manage file uploads.
     *
     * @param array $options
     * @return array
     */
    public function sanitizeSettings(array $options)
    {
        // catch manage_data; upload weightings
        if (!empty($_FILES["weighting-import"]["tmp_name"])) {
            self::weightingUploadHandler();
        }

        // catch Bulk index action
        if (isset($options['index_button'])) {
            if (is_indexing()) {
                self::settingNotice(
                    'The index cannot be refreshed until the current cycle has completed.',
                    'bulk-warning',
                    'warning'
                );
                return $options;
            }

            $options['indexing_began_at'] = time();
            $process_id = exec(
                "wp elasticpress index --setup --per-page=1 --allow-root > /dev/null 2>&1 & echo $!;"
            );
            $this->clearStats();
            self::settingNotice('Bulk indexing has started', 'bulk-warning', 'success');
        }

        // catch Bulk index action kill
        if (isset($options['index_kill'])) {
            $process_id = exec('pgrep -u www-data php$');
            if ($process_id > 0) {
                if (posix_kill($process_id, 9)) {
                    delete_transient('ep_wpcli_sync');
                    self::settingNotice(
                        'Done. The indexing process has been stopped',
                        'kill-success',
                        'success'
                    );

                    return $options;
                }
            }

            $message = 'Please investigate the cause further in a terminal, we could not determine the process ID';
            $process_id = exec('pgrep -u root php$');
            if ($process_id) {
                $message = 'Was the index started in a terminal? A root process ID was found: ' . $process_id;
            }
            self::settingNotice(
                'Killing the index process has failed.<br>' . $message,
                'bulk-warning'
            );
        }

        return $options;
    }

    /**
     * Uploads and processes the weighting data for use in ElasticPress. The uploaded file format must be JSON.
     * We make sure to remove the file once it has been used, maintaining a good level of security.
     * @return null
     */
    public static function weightingUploadHandler()
    {
        // Check file size
        if ($_FILES['weighting-import']['size'] > 5485760) {
            self::settingNotice('File imported is too big (5MB limit).', 'size-error');
            return;
        }

        $weighting_file = self::importLocation() . basename($_FILES['weighting-import']['name']);

        // Check the file was moved on the server
        if (!move_uploaded_file($_FILES['weighting-import']["tmp_name"], $weighting_file)) {
            self::settingNotice(
                'File not uploaded. There was a problem saving settings to file.',
                'move-error'
            );
            return;
        }

        $ep_weighting = json_decode(file_get_contents($weighting_file), true);

        // Check the JSON is decoded into an array.
        if (!is_array($ep_weighting)) {
            self::settingNotice(
                'File data corrupted (not provided as an array). DB not updated.',
                'json-error'
            );
            unlink($weighting_file);
            return;
        }

        // Check if DB was updated or not, convey message.
        if (!update_option('elasticpress_weighting', $ep_weighting)) {
            self::settingNotice(
                'File uploaded correctly however weighting is unchanged. This maybe due to duplicate data.',
                'db-error',
                'info'
            );
            unlink($weighting_file);
            return;
        }

        self::settingNotice(
            'Settings saved and weighting data imported successfully.',
            'import-success',
            'success'
        );
        unlink($weighting_file);
    }

    /**
     * Creates a new notice to be presented to the user. Used to pass information about the current process.
     * @param $notice
     * @param $code
     * @param string $type
     */
    private static function settingNotice($notice, $code, $type = 'error')
    {
        add_settings_error(
            'moj_es_settings',
            'moj-es' . $code,
            __($notice, 'wp-moj-elasticsearch'),
            $type
        );
    }

    /**
     * Simple wrapper to fetch the plugins data array
     * @return mixed|void
     * @uses get_option()
     */
    public function options()
    {
        return get_option($this->optionName(), []);
    }

    /**
     * Get the option group for the plugin settings. Used as 'page' in register_settings()
     * @return string
     */
    public function optionGroup()
    {
        return $this->prefix . $this->option_group;
    }

    /**
     * The settings name for our plugins option data.
     * Calling get_option() with this string will produce the plugins data.
     * @return string
     */
    public function optionName()
    {
        return $this->prefix . $this->option_name;
    }

    /**
     * Defines the import data location in the uploads directory.
     * @return string
     */
    public function importLocation()
    {
        $file_dir = get_temp_dir();
        return $file_dir . basename(plugin_dir_path(dirname(__FILE__, 1))) . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the stats stored from
     * @param string $key
     * @return array|string|null
     */
    public function getStats()
    {
        if (!file_exists($this->importLocation() . 'moj-bulk-index-stats.json')) {
            self::setStats([
                'total_bulk_requests' => 0,
                'total_stored_requests' => 0,
                'total_large_requests' => 0,
                'bulk_body_size' => 0,
                'bulk_request_errors' => [],
                'large_files' => []
            ]);
        }

        return (array)json_decode(file_get_contents($this->importLocation() . 'moj-bulk-index-stats.json'));
    }

    public function setStats($es_stats)
    {
        $handle = fopen($this->importLocation() . 'moj-bulk-index-stats.json', 'w');
        fwrite($handle, json_encode($es_stats));
        fclose($handle);
    }

    public function clearStats()
    {
        unlink($this->importLocation() . 'moj-bulk-index-stats.json');
    }

    /**
     * @param $schedules
     * @return array
     */
    public function addCronIntervals($schedules): array
    {
        $schedules['five_seconds'] = [
            'interval' => 5,
            'display' => esc_html__('Every 5 seconds')
        ];

        $schedules['one_minute'] = [
            'interval' => 60,
            'display' => esc_html__('Every Minute')
        ];

        return $schedules;
    }

    public function camelCaseToWords($string)
    {
        $regex = '/
              (?<=[a-z])      # Position is after a lowercase,
              (?=[A-Z])       # and before an uppercase letter.
            | (?<=[A-Z])      # Or g2of2; Position is after uppercase,
              (?=[A-Z][a-z])  # and before upper-then-lower case.
            /x';
        $words = preg_split($regex, $string);
        $count = count($words);
        if (is_array($words)) {
            $string = '';
            for ($i = 0; $i < $count; ++$i) {
                $string .= $words[$i] . " ";
            }
        }

        return rtrim($string);
    }

    /**
     * Calculate a human readable files size
     * @param $size
     * @return string
     */
    public function humanFileSize($size): string
    {
        $bit_sizes = ['GB' => 30, 'MB' => 20, 'KB' => 10]; // order is important
        $file_size = '0 bytes';
        foreach ($bit_sizes as $unit => $bit_size) {
            if ($size >= 1 << $bit_size) {
                $file_size = number_format($size / (1 << $bit_size), 2) . $unit;
                break;
            }
        }

        return $file_size;
    }

    public function rand($length = 10)
    {
        return substr(
            str_shuffle(
                str_repeat(
                    $alpha_num = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    ceil($length / strlen($alpha_num))
                )
            ),
            1,
            $length
        );
    }
}
