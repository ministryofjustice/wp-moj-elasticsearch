<?php
/**
 * Plugin name: WP MoJ ElasticSearch
 * Plugin URI:  https://github.com/ministryofjustice/wp-moj-elasticsearch
 * Description: WP interface for managing elastic search
 * Version:     3.1.0
 * Author:      Ministry of Justice Justice on the Web
 * Text domain: wp-moj-elasticsearch
 * Author URI:  https://peoplefinder.service.gov.uk/people/damien-wilson
 * License:     MIT License
 **/

define('ES_INDEX', 'intranet');

# load the traits
require_once('traits/ClientConnect.php');
require_once('traits/Debug.php');

# get the classes
require_once('classes/ElasticSearch.php');
require_once('classes/Insert.php');
require_once('classes/Query.php');

# build admin settings
require_once('classes/Admin.php');
$moj_es_settings = new \MOJElasticSearch\Admin();

# bind instances
$moj_insert = new \MOJElasticSearch\Insert();
$moj_query = new \MOJElasticSearch\Query();
