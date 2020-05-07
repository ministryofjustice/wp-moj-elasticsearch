<?php

namespace MOJElasticSearch\classes;

use Composer\Script\Event;

class Auto
{
    public static function load(Event $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        require $vendorDir . '/autoload.php';
    }
}
