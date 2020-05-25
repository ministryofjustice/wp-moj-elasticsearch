<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

use MOJElasticSearch\ElasticSearch;
use MOJElasticSearch\ManageData;
use MOJElasticSearch\Connection;

defined('ABSPATH') or exit;

class Admin
{
    public $prefix = 'moj_es';
    public $menu_slug = 'moj-es';
    public $text_domain = 'wp-moj-elasticsearch';

    public function __construct()
    {
        $this->actions();
    }

    public function actions()
    {
        add_action('admin_menu', [$this, 'settingsPage']);
    }

    public function settingsPage()
    {
        add_options_page(
            'MoJ ES',
            'MoJ ES',
            'manage_options',
            $this->menu_slug,
            [$this, 'init']
        );
    }

    /**
     * Set up the admin plugin page
     */
    public function init()
    {
        // Title section
        $title = __('MoJ ES', $this->text_domain);
        $title_admin = __('Extending the functionality of the ElasticPress plugin', $this->text_domain);
        ?>
        
        <style>.<?= $this->menu_slug ?> h2 {<?= $this->styles('heading', false) ?>}</style>
        
        <h1><?= $title ?> <small style="color:#aaaaaa">. <?= $title_admin ?></small></h1>
        
        <?php
                    
        /**
         * Tab generation and control
         */
        $activeTab = $_GET['tab'] ?? 'No value set';
        $activeTab = $_GET['tab'] ?? 'home';

        $homeTab = ($activeTab === 'home') ? 'nav-tab-active' : 'nav-tab-inactive';
        $connectionSettingsTab = ($activeTab === 'connection_settings') ? 'nav-tab-active' : 'nav-tab-inactive';
        $ManageDataTab = ($activeTab === 'manage_data') ? 'nav-tab-active' : 'nav-tab-inactive';

        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=moj-es&tab=home" class="nav-tab ' . $homeTab . '">Home</a>';
        echo '<a href="?page=moj-es&tab=connection_settings" class="nav-tab ' . $connectionSettingsTab . '">Connection settings</a>';
        echo '<a href="?page=moj-es&tab=manage_data" class="nav-tab ' . $ManageDataTab . '">Manage Data</a>';
        echo '</h2>';
        
        // Tab content display depending on what tab is active
        switch ($activeTab) {
            case 'manage_data':
                do_settings_sections('manage-data-section');
                do_settings_sections('manage-data-export-section');
                do_settings_sections('manage-data-import-section');
                break;
            case 'connection_settings':
                $Connection = new Connection;
                echo '<form action="options.php" method="post" class="<?= $this->menu_slug ?>">';
                settings_fields($Connection->_optionGroup());
                do_settings_sections($Connection->_optionGroup());
                submit_button();
                break;
            default:
                echo '<h4>General plugin details here.</h4>';
                break;
        }
    }

    public function styles($section, $include_style_attr = true)
    {
        $fields_all = 'min-width:300px';
        $styles = [
            'intro' => 'background-color:#2c5d94;color:#f1f2f2;padding: 12px 20px;display: inline-block;border: 1px solid #fff;max-width:50rem;min-width:30rem;',
            'fields' => [
                'all' => $fields_all . '',
                'text' => 'min-width:390px',
                'select' => $fields_all . '',
                'checkbox' => $fields_all . '',
                'password' => $fields_all . ''
            ],
            'heading' => 'color:#0d8730'
        ];

        $style = $this->_keyValue($styles, $section);
        if ($style) {
            if ($include_style_attr) {
                return ' style="' . $style . '"';
            }

            return $style;
        }

        return '';
    }

    private function _keyValue(array $arr, $key)
    {
        // is in base array?
        if (array_key_exists($key, $arr)) {
            return $arr[$key];
        }

        // check arrays contained in this array
        foreach ($arr as $element) {
            if (is_array($element)) {
                $value = $this->_keyValue($element, $key);
                if (!empty($value)) {
                    return $value;
                }
            }
        }

        return 'multiple';
    }
}
