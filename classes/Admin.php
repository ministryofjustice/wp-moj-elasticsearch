<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

class Admin
{
    use Auth, Debug;

    public $prefix = 'moj_es';
    public $option_name = '_settings';
    public $option_group = '_plugin';
    public $text_domain = 'wp-moj-elasticsearch';
    public static $tabs = [];
    public static $sections = [];

    public function __construct()
    {
        self::auth();
        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_menu', [$this, 'settingsPage']);
    }

    public function enqueue()
    {
        wp_enqueue_style('moj-es', plugins_url('../', __FILE__) . 'assets/css/main.css', []);
        wp_enqueue_script(
            'moj-es-js',
            plugins_url('../', __FILE__) . 'assets/js/main.js',
            ['jquery']
        );
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

        // Title section
        $title = __('MoJ ES', $this->text_domain);
        $title_admin = __('Extending the functionality of the ElasticPress plugin', $this->text_domain);
        ?>

        <h1><?= $title ?> <small class="sub-title">. <?= $title_admin ?></small></h1>

        <?php

        settings_errors();

        echo '<form action="options.php" method="post" class="moj-es" enctype="multipart/form-data">';

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
                if ($section['title']) {
                    echo "<h2>{$section['title']}</h2>\n";
                }

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
     * Intercepts when saving settings so we can sanitize, validate or manage file uploads.
     *
     * @param array $options
     * @return array
     */
    public static function sanitizeSettings(array $options)
    {
        // catch manage_data; upload weightings
        if (!empty($_FILES["weighting-import"]["tmp_name"])) {
            self::weightingUploadHandler();
        }

        // catch Kinesis index action
        if (isset($options['index_button'])) {
            // start indexing kinesis here
            self::settingNotice('We made it here but you cannot index via Kinesis yet!', 'bulk-error');
        }

        // catch keys unlock requests and reset lock
        // force lock any other time
        $options['access_keys_lock'] = 'yes';
        if (isset($options['access_keys_unlock']) && $options['access_keys_unlock'] === 'update keys') {
            self::settingNotice('Access keys are now unlocked.', 'access-error', 'warning');
            $options['access_keys_lock'] = null;
        }

        if (isset($options['access_keys_unlock']) && !empty($options['access_keys_unlock']) &&
            $options['access_keys_unlock'] !== 'update keys') {
            self::settingNotice(
                'Please enter a valid phrase to edit access keys',
                'access-error',
                'info'
            );
        }
        // holds the pass phrase, reset always
        $options['access_keys_unlock'] = null;

        return $options;
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
     * Creates a new notice to be presented to the user. Used to pass information about the current process.
     * @param $notice
     * @param $code
     * @param string $type
     */
    public static function settingNotice($notice, $code, $type = 'error')
    {
        add_settings_error(
            'moj_es_settings',
            'moj-es' . $code,
            __($notice, 'wp-moj-elasticsearch'),
            $type
        );
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
     * Defines the import data location in the uploads directory.
     * @return string
     */
    public static function importLocation()
    {
        $file_dir = wp_get_upload_dir()['basedir'] . DIRECTORY_SEPARATOR;
        return $file_dir . basename(plugin_dir_path(dirname(__FILE__, 1))) . DIRECTORY_SEPARATOR;
    }
}
