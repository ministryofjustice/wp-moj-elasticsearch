<?php

namespace MOJElasticSearch;

/**
 * ElasticPressHooks
 *
 * Hook into all EP hooks.
 *
 * @since 1.2.1
 */
class ElasticPressHooks
{
    use Debug;

    public function __construct()
    {
       // $this->alias_name = $alias->name;
        $this->hooks();
    }

    /**
     * Registers WP actions
     * This method is to be initialised on construct
     */
    public function hooks()
    {
        add_filter('ep_prepare_meta_excluded_public_keys', [$this, 'excludeMetaMappingFields'], 10, 2);
        add_filter('ep_post_sync_args_post_prepare_meta', [$this, 'removePostArgs'], 10, 2);
    }

    /**
     * Remove Post object meta that is not used
     * @param $post_args
     * @param $post_id
     * @return array
     */
    public function removePostArgs($post_args, $post_id): array
    {
        unset($post_args['post_author']);
        unset($post_args['comment_count']);
        unset($post_args['post_content_filtered']);
        unset($post_args['post_parent']);
        unset($post_args['comment_status']);
        unset($post_args['ping_status']);
        unset($post_args['menu_order']);
        unset($post_args['guid']);

        return $post_args;
    }

    /**
     * Exclude ElasticSearch mapping meta
     * @param $keys
     * @param $post
     * @return array
     */
    public function excludeMetaMappingFields($keys, $post): array
    {
        global $wpdb;

        $excluded = [
            'lbfw_likes',
            'lhs_menu_on',
            'dw_comments_on',
            'oasis_current_revision',
            'comment_disabled_status',
            'dw_hide_page_details',
            'dw_hq_guidance_bottom',
            'keywords',
            'related_docs_scanned',
            'related_docs',
            'is_imported',
            'dw_lhs_menu_on',
            'disable_banner',
            'dw_banner_link',
            'dw_banner_url',
            'dw_campaign_colour',
            'dw_campaign_skin',
            'dw_hq_guidance_bottom',
            'enable_agency_about_us',
            'dw_tag',
            'fork_from_post_id',
            'enable_moj_about_us',
            'enable_agency_about_us',
            'full_width_page_banner',
            'oasis_is_in_workflow',
            'moj_description',
            'amazonS3_cache',
            'amazonS3_info',
            'guidance_tabs'
        ];

        $query = "SELECT DISTINCT meta_key from `wp_postmeta`
        where meta_key like '%_html_content'
        OR meta_key like '%_links'
        OR meta_key like '%_sections'
        OR meta_key like '%_link_url'
        OR meta_key like '%_link_type'
        OR meta_key like 'content_section%'
        OR meta_key like 'built_in%'
        OR meta_key like 'choice_%'";

        // Store DB query in a transient to reduce SQL calls slowing indexing
        if (false === ($meta_keys = get_transient('moj_es_exclude_meta_fields'))) {
            $meta_keys = $wpdb->get_col($wpdb->prepare($query));
            set_transient('moj_es_exclude_meta_fields', $meta_keys, MONTH_IN_SECONDS);
        }

        $meta_keys = maybe_unserialize($meta_keys);

        foreach ($meta_keys as $meta_key) {
            $excluded[] = $meta_key;
        }

        return $excluded;
    }
}
