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
        $host = $this->getHost();
        if ($host) {
            # ES CONNECT
            return ClientBuilder::create()
                ->setHosts($host)
                ->setApiKey(
                    Admin::options('moj_es')['api_id'],
                    Admin::options('moj_es')['api_key']
                )
                ->build();
        }
    }

    public function getHost()
    {
        $options = Admin::options('moj_es');
        $host = $options['host'] ?? '';
        $port_ok = $options['host_port_ok'] ?? '';
        $port = ($port_ok === 'yes' ? ':' . $options['host_port'] ?? '9200' : '');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host . $port];
        }

        $parsed = parse_url($host);

        if (!isset($parsed['host'])) {
            return false;
        }

        return [$parsed['host'] . $port];
    }
}
