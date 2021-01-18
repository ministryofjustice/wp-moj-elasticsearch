<?php

/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: MoJ WP plugin to extend the functionality of the ElasticPress plugin
 * Version:     2.3.1
 * Authors:     Damien Wilson, Adam Brown, Robert Lowe
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://ministryofjustice.github.io/justice-on-the-web
 * License:     MIT License
 *
 **/

// Do not allow access outside of WP to plugin
defined('ABSPATH') || exit;
/**
 * Get the root of the plugin
 */
define('MOJ_ES_DIR', __DIR__);

require(MOJ_ROOT_DIR . '/vendor/autoload.php');

use MOJElasticSearch\ElasticPressHooks;
use MOJElasticSearch\Index;
use MOJElasticSearch\SignAmazonEsRequests;

new ElasticPressHooks();
new Index();
new SignAmazonEsRequests();
