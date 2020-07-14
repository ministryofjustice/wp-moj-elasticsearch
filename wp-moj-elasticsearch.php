<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: MoJ WP plugin to extend the functionality of the ElasticPress plugin
 * Version:     2.1.1
 * Authors:     Damien Wilson, Adam Brown
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://ministryofjustice.github.io/justice-on-the-web
 * License:     MIT License
 *
 **/

// Load all our classes from PSR4 autoloader
require(MOJ_ROOT_DIR . '/vendor/autoload.php');

use MOJElasticSearch\Options;
use MOJElasticSearch\Admin;
use MOJElasticSearch\Auth;
use MOJElasticSearch\ElasticPressHooks;
use MOJElasticSearch\Index;
use MOJElasticSearch\ManageData;
use MOJElasticSearch\SignAmazonEsRequests;

if (new Auth) {
    new Options;
    new Admin;
    new SignAmazonEsRequests;
    new ElasticPressHooks;
    new ManageData();
    new Index();
}
