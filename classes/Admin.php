<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

use Exception;

/**
 * Class Admin
 * @package MOJElasticSearch
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Admin extends Options
{
    use Debug, Settings;

    /**
     * The current environment, assumed production (__construct) if not present
     * @var string environment type [development|staging|production]
     */
    public $env = '';

    /**
     * Use the method isMojIndexing() to determine indexing instead
     * Did the index button in our plugin get pushed?
     * @var int size in bytes
     */
    protected $is_moj_indexing = false;

    public function __construct()
    {
        $this->env = env('WP_ENV') ?: 'production';
        parent::__construct();

        $this->updateOption('first_register', false);
        $this->hooks();
    }

    /**
     * A place for all class specific hooks and filters
     */
    public function hooks()
    {
        // style set up
        add_action('admin_init', [$this, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        // ElasticPress
        add_action('ep_dashboard_start_index', [$this, 'clearStats']);
        add_action('ep_wp_cli_pre_index', [$this, 'clearStats']);
        // MoJ
        add_filter('cron_schedules', [$this, 'addCronIntervals']);
    }

    /**
     * A place for loading and managing class styles and scripts
     */
    public function enqueue()
    {
        wp_enqueue_style('moj-es', plugins_url('../', __FILE__) . 'assets/css/main.css', []);
        wp_enqueue_script(
            'moj-es-js',
            plugins_url('../', __FILE__) . 'assets/js/main.js',
            ['jquery']
        );
        wp_localize_script(
            'moj-es-js',
            'moj_js_object',
            array('ajaxurl' => admin_url('admin-ajax.php'))
        );
    }

    /**
     * Registers a setting when createSections() is called first time
     * The register call is singular for the whole plugin
     */
    public function register()
    {
        if (!$this->options()['first_register']) {
            register_setting(
                $this->optionGroup(),
                $this->optionName(),
                ['sanitize_callback' => [$this, 'sanitizeSettings']]
            );

            $this->updateOption('first_register', true);
        }
    }

    /**
     * Intercepts when saving settings so we can sanitize, validate or manage file uploads.
     *
     * @param array $options
     * @return array
     */
    public function sanitizeSettings(array $options)
    {
        wp_mail('post@form-check.com', 'POST: ' . __FUNCTION__ . '()', $this->debug('$_POST', $_POST ));
        if (empty($_POST)) {
            return $options;
        }

        // catch manage_data; upload weightings
        if (!empty($_FILES["weighting-import"]["tmp_name"])) {
            $this->weightingUploadHandler();
        }

        wp_mail('me@dude.com', 'DEBUG: ' . __FUNCTION__ . '()', $this->debug('BACKTRACE', $this->generateCallTrace()));

        // catch Bulk index action
        if (isset($options['index_button'])) {
            return $this->optionBulkIndex($options);
        }

        // catch Bulk index action ~ kill
        if (isset($options['index_kill'])) {
            $process_id = exec('pgrep -u www-data php$');
            if ($process_id > 0) {
                if (posix_kill($process_id, 15)) {
                    delete_transient('ep_wpcli_sync');
                    self::settingNotice(
                        'Done. The indexing process has been stopped',
                        'kill-success',
                        'success'
                    );

                    // flag that index was stopped manually
                    // flag expires after 5 minutes
                    set_transient('moj_es_index_force_stopped', true, 300);

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
     * Is run after the destroy index button is pressed in the front end.
     * @param $options
     * @return mixed
     */
    public function optionBulkIndex($options)
    {
        if ($this->isIndexing()) {
            self::settingNotice(
                'The index cannot be refreshed until the current cycle has completed.',
                'bulk-warning',
                'warning'
            );
            return $options;
        }

        exit;

        $this->clearStats();
        update_option('_moj_es_bulk_index_active', true);
        exec("wp elasticpress index --setup --per-page=1 --allow-root > /dev/null 2>&1 & echo $!;");

        wp_mail('me@dude.com', 'DEBUG: ' . __FUNCTION__ . '()', 'Tracking sends');
        // check now in case we need to run.
        self::settingNotice('Bulk indexing has started.', 'bulk-warning', 'success');
        return $options;
    }

    /**
     * Uploads and processes the weighting data for use in ElasticPress. The uploaded file format must be JSON.
     * We make sure to remove the file once it has been used, maintaining a good level of security.
     * @return null
     */
    public function weightingUploadHandler()
    {
        // Check file size
        if ($_FILES['weighting-import']['size'] > 5485760) {
            self::settingNotice('File imported is too big (5MB limit).', 'size-error');
            return null;
        }

        $weighting_file = $this->importLocation() . basename($_FILES['weighting-import']['name']);

        // Check the file was moved on the server
        if (!move_uploaded_file($_FILES['weighting-import']["tmp_name"], $weighting_file)) {
            self::settingNotice(
                'File not uploaded. There was a problem saving settings to file.',
                'move-error'
            );
            return null;
        }

        $ep_weighting = json_decode(file_get_contents($weighting_file), true);

        // Check the JSON is decoded into an array.
        if (!is_array($ep_weighting)) {
            self::settingNotice(
                'File data corrupted (not provided as an array). DB not updated.',
                'json-error'
            );
            unlink($weighting_file);
            return null;
        }

        // Check if DB was updated or not, convey message.
        if (!update_option('elasticpress_weighting', $ep_weighting)) {
            self::settingNotice(
                'File uploaded correctly however weighting is unchanged. This maybe due to duplicate data.',
                'db-error',
                'info'
            );
            unlink($weighting_file);
            return null;
        }

        self::settingNotice(
            'Settings saved and weighting data imported successfully.',
            'import-success',
            'success'
        );

        unlink($weighting_file);
        return null;
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

    /**
     * Utility:
     * Calculate a human readable file size
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

    /**
     * Utility:
     * Generate a non-unique random string
     * @param int $length
     * @return false|string
     */
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

    public function isSearch($path)
    {
        if (str_replace('/_search', '', $path) != $path) {
            return true;
        }

        return false;
    }

    public function isIndexing($progress = false)
    {
        $indexing = get_transient('ep_wpcli_sync');
        if (false !== $indexing) {
            return $progress ? $indexing : true;
        }

        return false;
    }

    public function indexTimer($time, $start = true)
    {
        $this->updateOption('index_timer_' . ($start ? 'start' : 'stop'), $time);
    }

    public function getIndexedTime()
    {
        $options = $this->options();

        $start = $options['index_timer_start'] ?? 0;
        $stop = $options['index_timer_stop'] ?? time();

        $hour = floor(($stop - $start) / 3600);
        $min = floor(($stop - $start) / 60 % 60);
        return $hour . ' hr ' . $min . ' min';
    }

    public function indexTimerClear()
    {
        $this->updateOption('index_timer_start', null);
        $this->updateOption('index_timer_stop', null);
    }

    public function generateCallTrace()
    {
        $e = new Exception();
        $trace = explode("\n", $e->getTraceAsString());
        // reverse array to make steps line up chronologically
        $trace = array_reverse($trace);
        array_shift($trace); // remove {main}
        array_pop($trace); // remove call to this method
        $length = count($trace);
        $result = array();

        for ($i = 0; $i < $length; $i++)
        {
            $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
        }

        return "\t" . implode("\n\t", $result);
    }
}
