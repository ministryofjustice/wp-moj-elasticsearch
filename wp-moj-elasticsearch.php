<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: MoJ WP plugin to extend the functionality of the ElasticPress plugin
 * Version:     2.2.0
 * Authors:     Damien Wilson, Adam Brown, Robert Lowe
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://ministryofjustice.github.io/justice-on-the-web
 * License:     MIT License
 *
 **/

// Load all our classes from PSR4 autoloader
require(MOJ_ROOT_DIR . '/vendor/autoload.php');

use MOJElasticSearch\Options;
use MOJElasticSearch\Settings\Page;
use MOJElasticSearch\Alias;
use MOJElasticSearch\Admin;
use MOJElasticSearch\Auth;
use MOJElasticSearch\ElasticPressHooks;
use MOJElasticSearch\Index;
use MOJElasticSearch\Query;
use MOJElasticSearch\ManageData;
use MOJElasticSearch\SignAmazonEsRequests;

// settings
use MOJElasticSearch\Settings\IndexSettings;

if (new Auth) {
    $moj_es_alias = new Alias();
    $moj_es_admin = new Admin();
    new ElasticPressHooks($moj_es_alias);
    $moj_es_settings = new IndexSettings($moj_es_admin);
    new Options;
    new Page;
    new SignAmazonEsRequests;
    new ManageData();
    new Index($moj_es_settings, $moj_es_alias);
    new Query();
}
