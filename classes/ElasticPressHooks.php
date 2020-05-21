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
        $this->actions();
    }

    /**
     * Registers WP actions
     * This method is to be initialised on construct
     */
    public function actions()
    {
        add_filter('ep_elasticsearch_plugins', [$this, 'elasticsearchPlugins']);
        add_action('elasticpress_loaded', [$this, 'elasticPressLoaded']);
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



    public function elasticPressLoaded()
    {
        $fileName = 'ep_settings_weighting.json';
        $file = plugin_dir_path(__DIR__) . "settings/" . $fileName;

        if (!file_exists($file)) {
            $epWeighting = get_option('elasticpress_weighting');
            $jsonData = json_encode($epWeighting, JSON_PRETTY_PRINT);
            file_put_contents($file, $jsonData);
        } else {
            $epWeighting = json_decode(file_get_contents($file), true);
            update_option('elasticpress_weighting', $epWeighting);
        }
    }
}
