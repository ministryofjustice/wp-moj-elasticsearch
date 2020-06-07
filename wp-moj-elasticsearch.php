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

if (!defined('MOJ_ROOT_DIR')) {
    header("HTTP/1.1 403 Forbidden");
    return;
}

// Load all our classes from PSR4 autoloader
require(MOJ_ROOT_DIR . '/vendor/autoload.php');

use MOJElasticSearch\Auth;
use MOJElasticSearch\Admin;
use MOJElasticSearch\SignAmazonEsRequests;
use MOJElasticSearch\ElasticPressHooks;
use MOJElasticSearch\CliBulkIndex;
use MOJElasticSearch\Connection;
use MOJElasticSearch\ManageData;

if (new Auth) {
    new Admin;
    new SignAmazonEsRequests;
    new ElasticPressHooks;
    new CliBulkIndex;
    new ManageData();
    new Connection();
}
