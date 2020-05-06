<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: WP interface for managing elastic search
 * Version:     1.1.1
 * Author:      Ministry of Justice - Justice on the Web
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * License:     MIT License
 **/

defined('ABSPATH') or die('No humans allowed.');
define('ES_INDEX', 'intranet');

// Load all our classes from PSR4 autoloader
require __DIR__ . '/vendor/autoload.php';

use MOJElasticSearch\Classes\Admin;

// Check WP hasn't malfunctioned is ready to go.
if (!function_exists('add_action')) {
    exit;
}

$run = new Admin;
