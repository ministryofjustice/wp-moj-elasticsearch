<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: MoJ WP plugin to extend the functionality of the ElasticPress plugin
 * Version:     2.2.4
 * Authors:     Damien Wilson, Adam Brown, Robert Lowe
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://ministryofjustice.github.io/justice-on-the-web
 * License:     MIT License
 *
 **/

/**
 * Get the root of the plugin
 */
define('MOJ_ES_DIR', __DIR__);
/**
 * Disable ElasticPress dashboard sync
 */
define('EP_DASHBOARD_SYNC', false);

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
use MOJElasticSearch\AWS\Lambda;
use MOJElasticSearch\AWS\SignAmazonEsRequests;

// settings
use MOJElasticSearch\Settings\IndexSettings;

$moj_es_auth = new Auth();
if ($moj_es_auth->ok) {
    $moj_es_admin = new Admin();
    $moj_es_alias = new Alias($moj_es_admin);
    new ElasticPressHooks($moj_es_alias);
    $moj_es_settings = new IndexSettings($moj_es_admin, $moj_es_alias);
    new Lambda($moj_es_admin);
    new Options;
    new Page;
    new SignAmazonEsRequests;
    new ManageData();
    new Index($moj_es_settings, $moj_es_alias);
    new Query();
}
