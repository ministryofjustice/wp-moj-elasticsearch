<?php

namespace MOJElasticSearch;

class Admin
{
    public $prefix = 'moj_es';
    public $menu_slug = 'moj-es';

    const OPTION_NAME = '_settings';
    const OPTION_GROUP = '_plugin';

    public function __construct()
    {
        $this->actions();
    }

    public function actions()
    {
        add_action('admin_menu', [$this, 'settingsPage']);
        add_action('admin_init', [$this, 'settingsInit']);
    }

    public function settingsPage()
    {
        add_options_page(
            'MoJ Elastic Search',
            'MoJ Elastic Search',
            'manage_options',
            $this->menu_slug,
            [$this, 'mojEs']
        );
    }

    public function settingsInit()
    {
        register_setting($this->_optionGroup(), $this->optionName());

        add_settings_section(
            $this->prefix . '_host_section',
            __('Connection', 'wp-moj-elasticsearch'),
            [$this, 'hostSectionIntro'],
            $this->_optionGroup()
        );

        add_settings_field(
            $this->prefix . '_text_host_address',
            __('Host:', 'wp-moj-elasticsearch'),
            [$this, 'mojESTextHostRender'],
            $this->_optionGroup(),
            $this->prefix . '_host_section'
        );

        add_settings_field(
            $this->prefix . '_text_port_address',
            __('Port:', 'wp-moj-elasticsearch'),
            [$this, 'mojESTextPortRender'],
            $this->_optionGroup(),
            $this->prefix . '_host_section'
        );

        add_settings_field(
            $this->prefix . '_select_port_active',
            __('Port usage:', 'wp-moj-elasticsearch'),
            [$this, 'mojESSelectPortActiveRender'],
            $this->_optionGroup(),
            $this->prefix . '_host_section'
        );

        add_settings_section(
            $this->prefix . '_api_section',
            __('API Settings', 'wp-moj-elasticsearch'),
            [$this, 'apiSectionIntro'],
            $this->_optionGroup()
        );

        add_settings_field(
            $this->prefix . '_text_id',
            __('API ID:', 'wp-moj-elasticsearch'),
            [$this, 'mojESTextIdRender'],
            $this->_optionGroup(),
            $this->prefix . '_api_section'
        );

        add_settings_field(
            $this->prefix . '_text_key',
            __('API Key:', 'wp-moj-elasticsearch'),
            [$this, 'mojESTextKeyRender'],
            $this->_optionGroup(),
            $this->prefix . '_api_section'
        );
    }

    public function mojESTextHostRender()
    {
        $options = $this->_optionsArray();
        $description = __('Use a fully qualified domain name or an IPv4 address.', 'wp-moj-elasticsearch');

        ?>
        <input type="text" value="<?= $options['host'] ?: '' ?>" name='<?= $this->optionName()?>[host]'>
        <p><?= $description ?></p>
        <?php
    }

    public function mojESTextPortRender()
    {
        $options = $this->_optionsArray();
        $description = __('The default port is <em>9200</em>', 'wp-moj-elasticsearch');

        ?>
        <input type="text" value="<?= $options['host_port'] ?: '' ?>" name='<?= $this->optionName()?>[host_port]'>
        <p><?= $description ?></p>
        <?php
    }

    public function mojESSelectPortActiveRender()
    {
        $options = $this->_optionsArray();
        $description = __('You might say No if the port number isn\'t included in your host', 'wp-moj-elasticsearch');
        ?>
        <select name='<?= $this->optionName()?>[host_port_ok]'>
            <option value='' disabled="disabled">Use the port number?</option>
            <option value='no' <?php selected($options['host_port_ok'], 'no'); ?>>No</option>
            <option value='yes' <?php selected($options['host_port_ok'], 'yes'); ?>>Yes</option>
        </select>

        <?php
    }

    public function mojESTextIdRender()
    {
        $options = $this->_optionsArray();

        ?>
        <input type="text" value="<?= $options['api_id'] ?: '' ?>" name='<?= $this->optionName()?>[api_id]'>
        <?php
    }

    public function mojESTextKeyRender()
    {
        $options = $this->_optionsArray();
        ?>
        <input type="text" value="<?= $options['api_key'] ?: '' ?>" name='<?= $this->optionName()?>[api_key]'>
        <?php
    }


    public function hostSectionIntro()
    {
        $heading = __('Enter the host address info for your ES server', 'wp-moj-elasticsearch');
        $description = __('Please refer to ES documentation for guidance on locating your connection URL (host)', 'wp-moj-elasticsearch');
        echo '<strong>' . $heading . '</strong><br>' . $description;
    }

    public function apiSectionIntro()
    {
        $heading = __('Entering API access information', 'wp-moj-elasticsearch');
        $description = __('Create an API ID and Key in Kibana under the Security section "API Keys" and enter the details here', 'wp-moj-elasticsearch');
        echo '<strong>' . $heading . '</strong><br>' . $description;
    }

    public function mojEs()
    {
        $title = __('MoJ Elastic Search', 'wp-moj-elasticsearch');
        $title_admin = __('admin page', 'wp-moj-elasticsearch');
        ?>
        <form action='options.php' method='post'>

            <h1><?= $title ?> <small style="color:#aaaaaa">. <?= $title_admin ?></small></h1>

            <?php
            settings_fields($this->_optionGroup());
            do_settings_sections($this->_optionGroup());
            submit_button();
            ?>

        </form>
        <?php
    }

    private function _optionGroup()
    {
        return $this->prefix . self::OPTION_GROUP;
    }

    public function optionName()
    {
        return $this->prefix . self::OPTION_NAME;
    }

    private function _optionsArray()
    {
        return get_option($this->optionName());
    }

    public static function options($namespace)
    {
        return get_option($namespace . self::OPTION_NAME);
    }
}
