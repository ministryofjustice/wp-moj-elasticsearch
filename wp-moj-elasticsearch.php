<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: MoJ WP plugin to extend the functionality of the ElasticPress plugin
 * Version:     1.3.0
 * Authors:     Damien Wilson, Adam Brown
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://ministryofjustice.github.io/justice-on-the-web
 * License:     MIT License
 *
 **/

defined('ABSPATH') or die('No humans allowed.');
// Debug output related constant
define('DEBUG_ECHO', false);

// Check WP hasn't malfunctioned is ready to go.
if (!function_exists('add_action')) exit;

// Get site root to target installation of Composer's autoloader
global $root_dir;

if (empty($root_dir)) {
    trigger_error(
        'WP MoJ ElasticSearch expects Bedrock. 
        For your project add $root_dir = dirname(__DIR__); in your wp-config.php file.',
        E_USER_WARNING
    );
    return;
}

// Load all our classes from PSR4 autoloader
require $root_dir . '/vendor/autoload.php';

use MOJElasticSearch\Admin;
use MOJElasticSearch\SignAmazonEsRequests;
use MOJElasticSearch\ElasticPressHooks;
use MOJElasticSearch\Connection;
use MOJElasticSearch\ManageData;

new Admin;
new SignAmazonEsRequests;
new ElasticPressHooks;
new Connection;
new ManageData;
