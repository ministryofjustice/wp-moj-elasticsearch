<?php

namespace MOJElasticSearch;

/**
 * Class ElasticSearch
 * @package MOJElasticSearch
 */


class ElasticSearch
{
    use Debug;

    public $text_domain = 'wp-moj-elasticsearch';

    public function __construct()
    {
    }

    public static function canRun()
    {
        $critical_option = 'moj_es'; //Admin::options('moj_es');

        # host
        if (!isset($critical_option['host']) || empty($critical_option['host'])) {
            return false;
        }

        # api keys
        if (!isset($critical_option['api_id']) || empty($critical_option['api_id'])) {
            return false;
        }

        if (!self::live($critical_option)) {
            return false;
        }

        return true;
    }
}
