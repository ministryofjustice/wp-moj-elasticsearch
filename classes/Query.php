<?php

namespace MOJElasticSearch;

class Query extends Admin
{
    use Settings, Debug;

    public function __construct()
    {
        parent::__construct();
        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_menu', [$this, 'pageSettings'], 2);
        add_action('pre_get_posts', [$this, 'searchFields'], 1);
    }

    public function searchFields($query)
    {
        if (!is_admin() && $query->is_main_query()) {
            if ($query->is_search) {
                $query->set(
                    'search_fields',
                    array(
                        'post_title',
                        'post_content',
                        'post_excerpt',
                        'meta' => $this->getMetaFields() ?? []
                    )
                );
            }
        }
    }

    /**
     * @param bool $select_string
     * @return array|string
     */
    public function getMetaFields($no_query = false)
    {
        $meta_fields = $this->options()['search_meta_fields'] ?? null;
        $likes = explode("\n", $meta_fields);
        $likes = array_filter($likes, function ($value) {
            return !is_null($value) && trim($value) !== '';
        });

        $like_string = '';
        foreach ($likes as $key => $like) {
            if (empty(trim($like))) {
                continue;
            }
            $like_string .= " meta_key LIKE '" . trim($like) . "'" . ($key < (count($likes) - 1) ? ' OR' : '');
        }

        if (empty($like_string)) {
            return null;
        }

        $select_statement = "SELECT DISTINCT meta_key FROM wp_postmeta WHERE" . $like_string;

        if ($no_query) {
            return $select_statement;
        }

        global $wpdb;
        return $wpdb->get_col(
            $select_statement
        );
    }


    /**
     * This method is quite literally a space saving settings method
     *
     * Create your tab by adding to the $tabs global array with a label as the value
     * Configure a section with fields for that tab as arrays by adding to the $sections global array.
     *
     * @SuppressWarnings(PHPMD)
     */
    public function pageSettings()
    {
        // define section (group) and tabs
        $group = 'query';
        Admin::$tabs[$group] = 'Queries';

        // define fields
        $fields_index = [
            'search_meta_fields' => [$this, 'searchMetaFields']
        ];

        // fill the sections
        Admin::$sections[$group] = [
            $this->section([$this, 'queryIntro'], $fields_index)
        ];

        $this->createSections($group);
    }

    public function queryIntro()
    {
        $heading = __('The section below gives', $this->text_domain);

        $description = __('', $this->text_domain);
        echo '<div class="intro"><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function searchMetaFields()
    {
        $option = $this->options();
        $search_meta_fields = $option['search_meta_fields'] ?? '';
        ?>
        <p>List meta_keys here in the form of MySQL <em>LIKE</em> strings. You may use wildcards etc.<br>
            Put multiple strings on separate lines, without quotes.</p><br>
        <textarea
            name="<?= $this->optionName() ?>[search_meta_fields]"
            rows="5"
            cols="50"><?= $search_meta_fields ?></textarea>
        <br>
        <p><strong>Syntax check</strong></p>
        <pre><?= $this->getMetaFields(true) ?></pre>
        <?php
    }
}
