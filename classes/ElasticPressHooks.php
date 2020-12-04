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
    /**
     * Cache the index name
     * @var string
     */
    private $alias_name = '';

    public function __construct($alias)
    {
        $this->alias_name = $alias->name;
        $this->hooks();
    }

    /**
     * Registers WP actions
     * This method is to be initialised on construct
     */
    public function hooks()
    {
        add_filter('ep_elasticsearch_plugins', [$this, 'filterPlugins']);
        add_filter('ep_allowed_documents_ingest_mime_types', [$this, 'filterMimeTypes']);
        add_filter('ep_index_name', [$this, 'aliasName'], 10, 1);
        add_filter('ep_config_mapping', [$this, 'mapCustomConfig'], 10, 1);
        add_filter('ep_post_mapping', [$this, 'excludeMappingFields'], 10, 1);
        add_filter('ep_config_mapping_request', [$this, 'mapRequest'], 10, 1);
        add_filter('ep_prepare_meta_excluded_public_keys', [$this, 'excludeMetaMappingFields'], 10, 2);
    }

    /**
     * Add non-listed AWS ES plugins to filtered array
     * @param array|bool $es_plugins
     * @return array
     */
    public function filterPlugins($es_plugins): array
    {
        $es_plugins['ingest-attachment'] = sanitize_text_field(EP_VERSION) ?? true;

        return $es_plugins;
    }

    /**
     * Add or remove document formats. Filtering here will affect attachment indexing.
     * ES uses Apache Tika to convert binary documents into text and meta data.
     * For a full list of available formats, use the link below.
     *
     * @param $mime_types = [
     *           'pdf' => 'application/pdf',
     *           'ppt' => 'application/vnd.ms-powerpoint',
     *           'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
     *           'xls' => 'application/vnd.ms-excel',
     *           'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
     *           'doc' => 'application/msword',
     *           'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
     *       ];
     * @return array
     * @link https://tika.apache.org/1.24.1/formats.html#Full_list_of_Supported_Formats
     */
    public function filterMimeTypes($mime_types): array
    {
        // add the open document spreadsheet format
        $mime_types['ods'] = 'application/vnd.oasis.opendocument.spreadsheet';

        // remove a mime-type
        unset($mime_types['ods']);

        return $mime_types;
    }

    /**
     * MoJ ES uses index aliases rather than static index names. We do this to manage indexing more effectively
     * Also, using aliases allows us to keep front end searches routed through Elasticsearch
     *
     * Produces a meaningful alias name that points to generic indexes.
     *
     * @param string
     * @return string
     *
     * @uses str_replace()
     */
    public function aliasName(string $index_name): string
    {
        if (empty($this->alias_name)) {
            // local, dev, and staging + alias clean up
            $search = ['dockerwp', 'devwp', 'stagingwp', 'dsdiowp', '-post-1', '-term-1', '-user-1'];

            // track the use of our development environments, remove everything else...
            $replace = ['.local', '.dev', '.staging', ''];

            $index_name = str_replace($search, $replace, $index_name);

            // tag the live index
            $search = ['justicegovukwp'];
            $replace = ['.production'];
            $alias = str_replace($search, $replace, $index_name);

            // intranet.local
            // intranet.dev, etc.
            $this->alias_name = $alias;
            update_option('_moj_es_alias_name', $alias);
        }

        return $this->alias_name;
    }

    /**
     * Return detailed ElasticSearch response (not just ElasticPress plugin response)
     * @param array
     * @return array
     */
    public function mapRequest(array $request): array
    {
        echo 'ElasticSearch Response: ' . $request['body'] . PHP_EOL;
        return $request;
    }

    /**
     * Add custom configurations to ElasticSearch mapping
     * @param array
     * @return array
     */
    public function mapCustomConfig(array $mapping): array
    {
        // Check mapping exists in the expected data type
        if (!isset($mapping) || !is_array($mapping)) {
            echo 'Error mapping issue, mapping does not appear to exist.';
            return $mapping;
        }

        /**
         * Add custom synonym filter using AWS packages
         * https://docs.aws.amazon.com/elasticsearch-service/latest/developerguide/custom-packages.html
         * */
        $mapping['settings']['analysis']['filter']['moj_es_plugin_synonyms'] = [
            'type' => 'synonym',
            'synonyms_path' => 'analyzers/F124583324'
        ];

        // Create a custom analyzer we can add our own filters to as required
        $mapping['settings']['analysis']['analyzer']['moj_analyzer'] = [
            'type' => 'custom',
            'char_filter' => 'html_strip',
            "language" => "english",
            'tokenizer' => 'standard',
            'filter' => [
                'lowercase',
                'stop',
                'ewp_snowball',
                'moj_es_plugin_synonyms'
            ]
        ];

        $default_filter_array = $mapping['settings']['analysis']['analyzer']['default']['filter'];

        // Check default filter has been created by ElasticPress plugin as we need to add to it
        if (!isset($default_filter_array) || !is_array($default_filter_array)) {
            echo 'Error mapping issue, default filter is missing.';
            return $mapping;
        }

        /**
         * Add the custom synonym filter to the default EP plugin analyzer.
         * Synonym filter needs to be first in array as it is not compatible with ewp_word_delimiter being first
         * */
        $mapping['settings']['analysis']['analyzer']['default']['filter'] = array_merge(
            ['moj_es_plugin_synonyms'],
            $default_filter_array
        );

        return $mapping;
    }

    /**
     * Exclude ElasticSearch mapping fields within properties
     * @param array
     * @return array
     */
    public function excludeMappingFields(array $mapping): array
    {
        // Check mapping exists in the expected data type
        if (!isset($mapping) || !is_array($mapping)) {
            echo 'Error mapping issue, mapping does not appear to exist.';
            return $mapping;
        }

        $excluded = [
            'comment_count',
            'comment_status',
            'menu_order',
            'ping_status',
            'post_author',
            'post_content_filtered',
            'post_parent',
            'post_status',
            'post_modified',
            'guid'
        ];

        if (is_array($excluded)) {
            foreach ($excluded as $exclude) {
                unset($mapping['mappings']['properties']["$exclude"]);
            }
        }

        return $mapping;
    }

    /**
     * Exclude ElasticSearch mapping meta
     * @param array
     * @return array
     */
    public function excludeMetaMappingFields($keys, $post): array
    {
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

        global $wpdb;

       /* $query = "SELECT DISTINCT meta_key from `wp_postmeta`
        where meta_key like '%link_type' OR meta_key like '%sections' OR meta_key like '%link_url' OR meta_key like '%links'";*/

        $query = "SELECT DISTINCT meta_key from `wp_postmeta`
        where meta_key like '%_html_content'
        OR meta_key like '%_links'
        OR meta_key like '%_sections'
        OR meta_key like '%_link_url'
        OR meta_key like '%_link_type'
        OR meta_key like 'content_section%'
        OR meta_key like 'built_in%'
        OR meta_key like 'choice_%'";

        $meta_keys = $wpdb->get_col($wpdb->prepare($query));

        foreach ($meta_keys as $meta_key){
            $excluded[] = $meta_key;
        }

        return $excluded;
    }
}
