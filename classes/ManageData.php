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

defined('ABSPATH') or die('No humans allowed.');

class ManageData extends Admin
{
    const OPTION_NAME = '_settings2';
    const OPTION_GROUP = '_plugin2';

    public function __construct()
    {
        $this->actions();
    }

    public function actions()
    {
        add_action('admin_init', [$this, 'pageSettings']);
        add_action('admin_init', [$this, 'exportFile']);
    }
    
    public function pageSettings()
    {
        register_setting($this->_optionGroup2(), $this->optionName2());

        add_settings_section(
            $this->prefix . '_manage_data_section',
            __('Manage data and EP configuration', $this->text_domain),
            [$this, 'ManageDataPage'],
            $this->_optionGroup2()
        );
     }

    public function ManageDataPage()
    {

        submit_button(__( 'Export File', 'textdomain' ), 'primary', 'weighting', false, 'href="?page=moj-es&tab=manage_data&export=weighting"');
    }

    public function exportFile()
    {
        $fileName = 'ep_settings_weighting.json';
        $file = plugin_dir_path(__DIR__) . "settings/" . $fileName;

        if (isset($_GET['weighting']))
        {
            header("Content-type: application/json",true,200);
            header("Content-Disposition: attachment; filename=ep_settings_weighting.json");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo $file;
            exit();
          }
    }


    protected function _optionGroup2()
    {
        return $this->prefix . self::OPTION_GROUP;
    }

    public function optionName2()
    {
        return $this->prefix . self::OPTION_NAME;
    }
}
