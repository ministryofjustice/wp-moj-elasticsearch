<?php

namespace MOJElasticSearch;

trait FireHoseConnect
{
    protected $client = null;

    protected $options;

    public static $socket_failed = false;

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

    public static function live($options)
    {
        if (self::$socket_failed) {
            return false;
        }

        if (!@fsockopen($options['host'], ($options['port'] ?? '9200'))) {
            self::$socket_failed = true;
            return false;
        }

        return true;
    }
}
