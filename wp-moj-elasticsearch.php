<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: MoJ WP plugin to extend the functionality of the ElasticPress plugin
 * Version:     2.0.0
 * Authors:     Damien Wilson, Adam Brown
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://ministryofjustice.github.io/justice-on-the-web
 * License:     MIT License
 *
 **/

// Debug output related constant
define('DEBUG_ECHO', true);

// Get site root to target installation of Composer's autoloader
global $root_dir;
if (empty($root_dir)) {
    trigger_error(
        'WP MoJ ElasticSearch expects Bedrock.
        For your project add $root_dir = dirname(__DIR__); in your wp-config.php file.',
        E_USER_WARNING
    );
    // try and guess the path
    $root_dir = '/bedrock';
    if (!file_exists($root_dir)) {
        return;
    }
}

// Load all our classes from PSR4 autoloader
require $root_dir . '/vendor/autoload.php';

use MOJElasticSearch\Admin;
use MOJElasticSearch\SignAmazonEsRequests;
use MOJElasticSearch\ElasticPressHooks;
use MOJElasticSearch\CliBulkIndex;
use MOJElasticSearch\Connection;
use MOJElasticSearch\ManageData;

new Admin;
new SignAmazonEsRequests;
new ElasticPressHooks;
new CliBulkIndex;
new ManageData();
new Connection();
