# WP MoJ ElasticSearch
[![Maintainability](https://api.codeclimate.com/v1/badges/4f7226e63446f09b363c/maintainability)](https://codeclimate.com/github/ministryofjustice/wp-moj-elasticsearch/maintainability) [![Test Coverage](https://api.codeclimate.com/v1/badges/4f7226e63446f09b363c/test_coverage)](https://codeclimate.com/github/ministryofjustice/wp-moj-elasticsearch/test_coverage)

WP MoJ ElasticSearch is a companion WordPress plugin to be installed with the ElasticPress plugin. It allows for greater customisation and enhancements unique to our AWS ElasticSearch configuration.

## Features
* Signs requests via AWS so that ElasticPress can communicate with our AWS ES domain.
* Filters unused index fields out. We discovered our site was exceeding the 5000 field ElasticPress limit when indexing resulting in posts not being indexed. We have added in code to remove unneeded fields and reduce the field count to under < 3000.
* Modify ElasticPress default index name. To help with our many environments and index tracking, we've introduced our own random index name generation. This hooks into the EP index name hook `ep_index_name`. The index naming pattern follows `<env>.<namespace>.<random-generated-name>`

## Issues
Raise issues via
<a href="https://github.com/ministryofjustice/wp-moj-elasticsearch/issues">https://github.com/ministryofjustice/wp-moj-elasticsearch/issues</a>

## Installation
Download this repository, unzip and copy the folder into your Wordpress plugin file directory.

## Prerequesites
* Using AWS managed ElasticSearch
* Wordpress and ElasticPress plugin

## Coding guidelines

This plugin follows

* Standards set by the Wordpress organisation https://codex.wordpress.org/Writing_a_Plugin.
* PHP Framework Interop Group's standards http://www.php-fig.org/
* ElasticPress plugin classes and framework http://10up.github.io/ElasticPress/

## Developer notes
Command line to get the number of fields in an index.
`curl -s -XGET <aws index URL here>/<index name>/_mapping?pretty | grep type | wc -l`

### Automated linting and PHP code sniffing
We have a Git Action setup that lints, sniffs and then commits the linted PHP code in this plugin when anything is pushed to the repo.

### Manual testing on your local machine
The PHP Mess Detector and PHP Code Sniffer is available for us to assist in creating great code.

Please run `composer test` before committing a PR and, if you'd rather programmatically fix the issues produced by code-sniffer, `composer test-fix` can help format your code according to PSR.
