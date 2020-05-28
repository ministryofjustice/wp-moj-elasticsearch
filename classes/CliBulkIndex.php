<?php

namespace MOJElasticSearch;

use MOJElasticSearch\Middleware\AuthenticateRequest;

class CliBulkIndex
{
    public function __construct()
    {
        $this->output();
    }

    public function output()
    {
        echo '<pre>'  . print_r('I am coming from output in CliBulkIndex', true) . '</pre>';
    }
}
