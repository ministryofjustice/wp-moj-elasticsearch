<?php

namespace MOJElasticSearch;

class ElasticPressHooks
{
    public function __construct()
    {
        $this->actions();
    }

    /**
     * Registers WP actions
     * This method is to be initialised on construct
     */
    public function actions()
    {
        add_filter('ep_elasticsearch_plugins', [$this, 'elasticsearchPlugins']);
    }

    /**
     * Add non-listed ES plugins to filtered array
     * @param array|bool $es_plugins
     * @return array
     */
    public function elasticsearchPlugins($es_plugins): array
    {
        $es_plugins['ingest-attachment'] = true;

        return $es_plugins;
    }
}
