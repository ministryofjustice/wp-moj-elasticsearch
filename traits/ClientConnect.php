<?php

namespace MOJElasticSearch;

use Elasticsearch\ClientBuilder;

trait ClientConnect
{
    protected $client = null;

    public function client()
    {
        return $this->client ?? $this->setClient();
    }

    public function setClient()
    {
        # ES CONNECT
        $hosts = [
            env('ES_HOST_IP') . ':9200'
        ];

        return ClientBuilder::create()
            ->setHosts($hosts)
            ->setApiKey(env('ES_API_ID'), env('ES_API_KEY'))
            ->build();
    }
}
