<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: WP interface for managing elastic search
 * Version:     1.1.1
 * Author:      Ministry of Justice - Justice on the Web
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://ministryofjustice.github.io/justice-on-the-web
 * License:     MIT License
 *
 * Last but not least, shout out to Damien the founding author :)
 **/

defined('ABSPATH') or die('No humans allowed.');

global $root_dir;

// Load all our classes from PSR4 autoloader
require $root_dir . '/vendor/autoload.php';

use MOJElasticSearch\Admin;

// Check WP hasn't malfunctioned is ready to go.
if (!function_exists('add_action')) {
    exit;
}

$run = new Admin;
