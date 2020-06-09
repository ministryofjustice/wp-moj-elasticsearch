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

class ManageData extends Admin
{
    use Settings, Debug;

    public function __construct()
    {
        parent::__construct();
        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_init', [$this, 'pageSettings'], 1);
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function pageSettings()
    {
        $group = 'manage_data';
        Admin::$tabs[$group] = 'EP Configurations';
        Admin::$sections[$group] = [
            [
                'id' => 'weighting_data_section',
                'title' => 'Weighting',
                'callback' => [$this, 'manageWeightingIntro'],
                'fields' => [
                    'weighting-import' => ['title' => 'Choose a file:', 'callback' => [$this, 'importEPWeights']],
                    'weighting-export' => ['title' => 'Current Weights', 'callback' => [$this, 'outputCurrentWeights']]
                ]
            ]
        ];

        $this->createSections($group);
    }

    public function manageWeightingIntro()
    {
        $heading = __('Import and Backup Data', $this->text_domain);
        $description = __('Manage settings and configurations for ElasticPress.', $this->text_domain);
        echo '<div class="intro"><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    /**
     * Export. No need to generate an export. Exact data exists in an EP option.
     */
    public function outputCurrentWeights()
    {
        $ep_weighting = get_option('elasticpress_weighting') ?? [];
        $json_data = json_encode($ep_weighting, JSON_PRETTY_PRINT);

        echo $json_data ?? '';
    }

    /**
     * Import JSON file
     */
    public function importEPWeights()
    {
        echo '<strong>WARNING:</strong> Updating settings with a file selected will overwrite';
        echo '<a href="/wp/wp-admin/admin.php?page=elasticpress-weighting">ElasticPress weightings</a>.';
        echo '<br>Please take a back-up of data from below before doing this.<br><br>';
        echo '<input type="file" name="weighting-import" class="button-primary" /><br><br>';

        // Check and make dir if it doesn't exist
        wp_mkdir_p(parent::importLocation());
    }

    /**
     * Export JSON file
     */
    public function exportEpWeighting()
    {
        echo '<p>Export and Import weighting data from the ElasticPress plugin.</p>';
    }
}
