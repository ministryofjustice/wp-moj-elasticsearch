<?php

namespace MOJElasticSearch;

/**
 * Class ElasticSearch
 * @package MOJElasticSearch
 */
class ElasticSearch
{
    use ClientConnect, Debug;

    public function __construct()
    {
        return $this->client();
    }
}
