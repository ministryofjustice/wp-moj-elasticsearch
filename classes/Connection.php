<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

/**
 * Manages connections and data flow with AWS Kinesis
 * Class Connection
 * @package MOJElasticSearch
 * @SuppressWarnings(PHPMD)
 */
class Connection extends Admin
{
    /**
     * This class requires settings fields in the plugins dashboard.
     * Include the Settings trait
     */
    use Settings, Debug;

    public function __construct()
    {
        parent::__construct();
        $this->hooks();
    }

    public function hooks()
    {
        //add_action('admin_menu', [$this, 'pageSettings'], 1);
    }
}
