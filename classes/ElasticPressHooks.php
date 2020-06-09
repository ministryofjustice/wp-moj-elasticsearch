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
    public function __construct()
    {
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
        add_action('ep_dashboard_start_index', [$this, 'preventDashboardIndex']);
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

    public function preventDashboardIndex()
    {
        // TODO: prevent dashboard sync from ElasticPress UI
    }
}
