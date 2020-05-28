<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

class Connection extends Admin
{
    const OPTION_NAME = '_connection_settings';
    const OPTION_GROUP = '_moj_elasticsearch';

    public function __construct()
    {
        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_init', [$this, 'pageSettings']);
    }

    public function pageSettings()
    {
        register_setting($this->_optionGroup(), $this->optionName());

        add_settings_section(
            $this->prefix . '_host_section',
            __('Firehose Connection', $this->text_domain),
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

    protected function _optionGroup()
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
}
