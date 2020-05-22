<?php

namespace MOJElasticSearch;

/**
 * Class ElasticSearch
 * @package MOJElasticSearch
 */

use MOJElasticSearch\Admin;

class ElasticSearch
{
    use ClientConnect;
    use Debug;

    public function __construct()
    {
        // if (ElasticSearch::canRun()) {
        //     return $this->client();
        // }

        return null;
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
