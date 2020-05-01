<?php

namespace MOJElasticSearch;

class Admin
{
    public $prefix = 'moj_es';
    public $menu_slug = 'moj-es';
    public $text_domain = 'wp-moj-elasticsearch';

    const OPTION_NAME = '_settings';
    const OPTION_GROUP = '_plugin';

    public function __construct()
    {
        if (!ElasticSearch::live(self::options($this->prefix))) {
            add_action('admin_notices', [$this, 'socketFailureNotice']);
        }

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
            'MoJ Elasticsearch',
            'MoJ Elasticsearch',
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
            __('Connection', $this->text_domain),
            [$this, 'hostSectionIntro'],
            $this->_optionGroup()
        );

        add_settings_field(
            $this->prefix . '_text_host_address',
            __('Host:', $this->text_domain),
            [$this, 'mojESTextHostRender'],
            $this->_optionGroup(),
            $this->prefix . '_host_section'
        );

        add_settings_field(
            $this->prefix . '_text_port_address',
            __('Port:', $this->text_domain),
            [$this, 'mojESTextPortRender'],
            $this->_optionGroup(),
            $this->prefix . '_host_section'
        );

        add_settings_field(
            $this->prefix . '_select_port_active',
            __('Port usage:', $this->text_domain),
            [$this, 'mojESSelectPortActiveRender'],
            $this->_optionGroup(),
            $this->prefix . '_host_section'
        );

        add_settings_section(
            $this->prefix . '_api_section',
            __('API Settings', $this->text_domain),
            [$this, 'apiSectionIntro'],
            $this->_optionGroup()
        );

        add_settings_field(
            $this->prefix . '_text_id',
            __('API ID:', $this->text_domain),
            [$this, 'mojESTextIdRender'],
            $this->_optionGroup(),
            $this->prefix . '_api_section'
        );

        add_settings_field(
            $this->prefix . '_text_key',
            __('API Key:', $this->text_domain),
            [$this, 'mojESTextKeyRender'],
            $this->_optionGroup(),
            $this->prefix . '_api_section'
        );

        if (ElasticSearch::canRun()) {
            add_settings_section(
                $this->prefix . '_bulk_section',
                __('Bulk Inserts', $this->text_domain),
                [$this, 'bulkSectionIntro'],
                $this->_optionGroup()
            );

            add_settings_field(
                $this->prefix . '_multi_bulk',
                __('Post types to import', $this->text_domain),
                [$this, 'mojESMultiBulkRender'],
                $this->_optionGroup(),
                $this->prefix . '_bulk_section'
            );

            add_settings_field(
                $this->prefix . '_checkbox_bulk',
                __('Bulk insert now?', $this->text_domain),
                [$this, 'mojESCheckboxBulkRender'],
                $this->_optionGroup(),
                $this->prefix . '_bulk_section'
            );
        }
    }

    public function mojESTextHostRender()
    {
        $options = $this->_optionsArray();
        $description = __('Use an AWS endpoint url or an IPv4 address.', $this->text_domain);

        ?>
        <input<?= $this->styles('text') ?> type="text" value="<?= $options['host'] ?: '' ?>" name='<?= $this->optionName() ?>[host]'>
        <p><?= $description ?></p>
        <?php
    }

    public function mojESTextPortRender()
    {
        $options = $this->_optionsArray();
        $description = __('Default port is <em>9200</em>', $this->text_domain);

        ?>
        <input type="text" value="<?= $options['host_port'] ?: '' ?>" name='<?= $this->optionName() ?>[host_port]'>
        <p><?= $description ?></p>
        <?php
    }

    public function mojESSelectPortActiveRender()
    {
        $options = $this->_optionsArray();
        $description = __('For instance; you might select No if the port number isn\'t needed in your host name', $this->text_domain);
        ?>
        <select name='<?= $this->optionName() ?>[host_port_ok]'>
            <option value='' disabled="disabled">Use the port number?</option>
            <option value='no' <?php selected($options['host_port_ok'], 'no'); ?>>No</option>
            <option value='yes' <?php selected($options['host_port_ok'], 'yes'); ?>>Yes</option>
        </select>
        <p><?= $description ?></p>
        <?php
    }

    public function mojESTextIdRender()
    {
        $options = $this->_optionsArray();

        ?>
        <input<?= $this->styles('text') ?> type="password" value="<?= $options['api_id'] ?: '' ?>" name='<?= $this->optionName() ?>[api_id]'>
        <?php
    }

    public function mojESTextKeyRender()
    {
        $options = $this->_optionsArray();
        ?>
        <input<?= $this->styles('text') ?> type="password" value="<?= $options['api_key'] ?: '' ?>" name='<?= $this->optionName() ?>[api_key]'>
        <?php
    }

    public function mojESCheckboxBulkRender()
    {
        $options = $this->_optionsArray();
        ?>
        <input type='checkbox' name='<?= $this->optionName() ?>[bulk_activate]'
               value='yes' <?= checked('yes', $options['bulk_activate'] ?? '') ?>>
        <?php
    }

    public function mojESMultiBulkRender()
    {
        $options = $this->_optionsArray();
        $post_types_bulk = $options['bulk_post_types'] ?? [];
        $post_types = get_post_types(['public' => true, 'exclude_from_search' => false], 'objects');

        $output = '';
        foreach ($post_types as $type) {
            $label = ucwords(str_replace(['_', '-'], ' ', $type->name));
            $selected = '';
            if (!empty($post_types_bulk)) {
                $selected = (in_array($type->name, $post_types_bulk) ? ' selected="selected"' : '');
            }
            $output .= '<option value="' . $type->name . '"' . $selected . '>' . $label . '</option>';
        }
        ?>
        <select<?= $this->styles('select') ?> name='<?= $this->optionName() ?>[bulk_post_types][]' multiple="multiple" size="8">
            <option value='' disabled="disabled">Select multiple</option>
            <?= $output ?>
        </select>
        <p><small>There are <?= count($post_types) ?> to choose from.</small></p>
        <?php
    }

    public function hostSectionIntro()
    {
        $heading = __('Enter the host address info for your ES server', $this->text_domain);
        $description = __('Please note that this entry will assist in signing AWS requests to the ES server and is identical to the', $this->text_domain);
        echo '<div' . $this->styles('intro') . '><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function apiSectionIntro()
    {
        $heading = __('Entering API access information', $this->text_domain);
        $description = __('Create an API ID and Key in Kibana under the Security section "API Keys" and enter the details here', $this->text_domain);
        echo '<div' . $this->styles('intro') . '><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function bulkSectionIntro()
    {
        $heading = __('Manage bulk inserts into Elasticsearch indexes', $this->text_domain);
        $description = __('Start by selecting the post types you would like to sync with ES.', $this->text_domain);
        echo '<div' . $this->styles('intro') . '><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function mojEs()
    {
        $title = __('MoJ Elasticsearch', $this->text_domain);
        $title_admin = __('admin page', $this->text_domain);
        ?>
        <style>
            .<?= $this->menu_slug ?> h2 {
                <?= $this->styles('heading', false) ?>
            }
        </style>

        <form action="options.php" method="post" class="<?= $this->menu_slug ?>">

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

    public function updateOption($key, $value)
    {
        $options = $this->_optionsArray();

        if (!isset($options[$key])) {
            return false;
        }

        $options[$key] = $value;
        update_option($this->optionName(), $options);
    }

    public function socketFailureNotice()
    {
        $class = 'notice notice-error';
        $message = __('Please check your settings below. A connection to the ES server cannot be established.', $this->text_domain);

        //printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    public function styles($section, $include_style_attr = true)
    {
        $fields_all = 'min-width:300px';
        $styles = [
            'intro' => 'background-color:#2c5d94;color:#f1f2f2;padding: 12px 20px;display: inline-block;border: 1px solid #fff;max-width:50rem;min-width:30rem;',
            'fields' => [
                'all' => $fields_all .'',
                'text' => 'min-width:390px',
                'select' => $fields_all .'',
                'checkbox' => $fields_all .'',
                'password' => $fields_all .''
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
