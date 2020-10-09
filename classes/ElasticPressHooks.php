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
    /**
     * Cache the index name
     * @var string
     */
    private $alias_name = '';

    public function __construct()
    {
        $alias = new Alias();
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
        add_filter('ep_config_mapping_request', [$this, 'mapRequest'], 10, 1);
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
}
