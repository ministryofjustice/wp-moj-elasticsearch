<?php

namespace MOJElasticSearch;

use Elasticsearch\ClientBuilder;

trait ClientConnect
{
    protected $client = null;

    protected $options;

    public function client()
    {
        return $this->client ?? $this->setClient();
    }

    public function setClient()
    {
        # ES CONNECT
        return ClientBuilder::create()
            ->setHosts($this->getHost())
            ->setApiKey(
                Admin::options('moj_es')['api_id'],
                Admin::options('moj_es')['api_key']
            )
            ->build();
    }

    public function getHost()
    {
        $options = Admin::options('moj_es');
        $host = $options['host'];
        $port = ($options['host_port_ok'] === 'yes' ? ':' . $options['host_port'] ?? '9200' : '');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host . $port];
        }

        $parsed = parse_url($host);

        return [$parsed['host'] . $port];
    }
}
