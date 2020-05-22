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
        add_action('admin_init', [$this, 'settingsInit2']);
    }
    
    public function settingsInit2()
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
        echo '<h3>Export</h3><br>add button</div>';
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
