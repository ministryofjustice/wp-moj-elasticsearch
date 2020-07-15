<?php
/**
 * Modularised settings across the plugin, in the WordPress way
 */

namespace MOJElasticSearch;

use function ElasticPress\Utils\is_indexing;

trait Settings
{
    public function createSections($group)
    {
        if (empty($group) || !isset(Admin::$sections[$group]) || !is_array(Admin::$sections[$group])) {
            return;
        }

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
}
