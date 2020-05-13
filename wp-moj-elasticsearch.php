<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: WP interface for managing elastic search
 * Version:     1.2.2
 * Author:      Ministry of Justice - Justice on the Web
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://ministryofjustice.github.io/justice-on-the-web
 * License:     MIT License
 *
 * Last but not least, shout out to Damien the founding author :)
 **/

defined('ABSPATH') or die('No humans allowed.');

// debug output related constant
define('DEBUG_ECHO', false);

// Check WP hasn't malfunctioned is ready to go.
if (!function_exists('add_action')) {
    exit;
}

global $root_dir;
if (empty($root_dir)) {
    trigger_error(
        'WP MoJ ElasticSearch expects Bedrock. For your project simply add $root_dir = dirname(__DIR__); in your wp-config.php file.',
        E_USER_WARNING
    );
    return;
}


// Load all our classes from PSR4 autoloader
require $root_dir . '/vendor/autoload.php';

use MOJElasticSearch\Admin;
use MOJElasticSearch\SignAmazonEsRequests;
use MOJElasticSearch\ElasticPressHooks;

new Admin();
new SignAmazonEsRequests();
new ElasticPressHooks();
