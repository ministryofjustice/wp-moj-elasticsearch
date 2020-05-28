<?php

/**
 * MoJ Elasticpress plugin
 *
 * Manage data and EP configuration
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

use MOJElasticSearch\ElasticSearch;

class ManageData extends Admin
{
    public $export_file_name = 'ep_settings_weighting.json';
    public $weighting_file = '';

    public function __construct()
    {
        parent::__construct();
        $this->hooks();
        $this->weighting_file = $this->exportDirectory() . $this->export_file_name;
    }

    public function hooks()
    {
        add_action('admin_init', [$this, 'pageSettings']);
    }

    public function pageSettings()
    {
        add_settings_section(
            $this->prefix . '_manage_data_section',
            __('Manage data and EP configuration', $this->text_domain),
            [$this, 'manageDataIntro'],
            'manage-data-section'
        );

        add_settings_section(
            $this->prefix . '_manage_data_import_section',
            __('Export data:', $this->text_domain),
            [$this, 'exportEpWeighting'],
            'manage-data-export-section'
        );

        add_settings_section(
            $this->prefix . '_manage_data_import_section',
            __('Import data:', $this->text_domain),
            [$this, 'importEpWeighting'],
            'manage-data-import-section'
        );
    }

    public function manageDataIntro()
    {
        echo 'Mange settings and configurations in ElasticPress. Import and Export data.';
    }

    /**
     * Import JSON file
     */
    public function importEpWeighting()
    {
        echo '<form method="POST" enctype="multipart/form-data">';
        echo '<label><strong>WARNING:</strong> This will overwrite ElasticPress settings.</label><br>';
        echo '<label>Upload your JSON file:</label>';
        echo '<input type="file" name="file-weighting-json" />';
        echo '<input type="submit" name="submit-wp-weighting-json" value="Import File"><br><br>';
        echo '</form>';

        // Check and make dir if it doesn't exist
        wp_mkdir_p($this->exportDirectory());

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['submit-wp-weighting-json'])) {
                // Check file size
                if ($_FILES['file-weighting-json']['size'] > 5485760) {
                    echo '<strong>File imported is too big (5MB limit).</strong>';
                    return;
                }

                $import_notification = move_uploaded_file($_FILES['file-weighting-json']["tmp_name"], $this->weighting_file);

                // Check tmp file has been moved to plugin location
                if ($import_notification) {
                    echo '<strong>Uploaded to plugin successful.<strong><br>';
                } else {
                    echo '<strong>Upload to plugin failed, issue moving file to plugin directory. Likely permission issue.<strong><br>';
                    return;
                }

                $ep_weighting = json_decode(file_get_contents($this->weighting_file), true);

                // Check the JSON is decoded into an array.
                if (!is_array($ep_weighting)) {
                    echo '<strong>File data corrupted (not provided as an array). DB not updated.<strong><br>';
                    return;
                }

                $db_update_notification = update_option('elasticpress_weighting', $ep_weighting);

                // Check if DB was updated or not, convay message.
                if ($db_update_notification) {
                    echo '<strong>Database updated.<strong><br>';
                } else {
                    echo '<strong>Database not updated.<br>JSON data has not changed or
                    there was an issue updating the "elasticpress_weighting" WP option field.<strong><br>';
                }
            }
        }
    }

    /**
     * Export JSON file
     */
    public function exportEpWeighting()
    {
        echo '<form method="POST">';
        submit_button(__('Export', $this->text_domain), 'secondary', 'export-save-settings');
        echo '</form>';

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['export-save-settings'])) {
                $ep_weighting = get_option('elasticpress_weighting');
                $json_data = json_encode($ep_weighting, JSON_PRETTY_PRINT);

                // Print JSON data to screen
                echo $json_data;
            }
        }
    }

    public function exportDirectory()
    {
        $ds = DIRECTORY_SEPARATOR;
        $file_dir = wp_get_upload_dir()['basedir'];
        return $file_dir . $ds . $this->text_domain . $ds;
    }
}
