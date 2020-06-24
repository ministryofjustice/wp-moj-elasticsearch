<?php
/**
 * Modularised settings across the plugin, in the WordPress way
 */

namespace MOJElasticSearch;

trait Settings
{
    public $settings_registered = false;

    /**
     * Registers a setting when createSections() is called first time
     * The register call is singular for the whole plugin
     */
    public function register()
    {
        if (!$this->settings_registered) {
            register_setting(
                $this->optionGroup(),
                $this->optionName(),
                ['sanitize_callback' => ['MOJElasticSearch\Admin', 'sanitizeSettings']]
            );
            $this->settings_registered = true;
        }
    }

    public function createSections($group)
    {
        if (empty($group) || !isset(Admin::$sections[$group]) || !is_array(Admin::$sections[$group])) {
            return;
        }

        $this->register();

        foreach (Admin::$sections[$group] as $section) {
            add_settings_section(
                $this->prefix . '_' . $section['id'],
                __($section['title'], $this->text_domain),
                $section['callback'],
                $this->optionGroup()
            );

            if (is_array($section['fields'])) {
                foreach ($section['fields'] as $field_id => $field) {
                    add_settings_field(
                        $this->prefix . '_' . $field_id,
                        __($field['title'], $this->text_domain),
                        $field['callback'],
                        $this->optionGroup(),
                        $this->prefix . '_' . $section['id']
                    );
                }
            }
        }
    }

    /**
     * Update a setting value using the WP Settings API
     * @param $key
     * @param $value
     * @return bool
     */
    public function updateOption($key, $value)
    {
        $options = $this->options();

        $options[$key] = $value;
        return update_option($this->optionName(), $options);
    }

    /**
     * Delete a setting value using the WP Settings API
     * @param $key
     * @return bool
     */
    public function deleteOption($key)
    {
        $options = $this->options();

        unset($options[$key]);
        return update_option($this->optionName(), $options);
    }
}
