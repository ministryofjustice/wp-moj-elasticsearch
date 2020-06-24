<?php

namespace MOJElasticSearch;

/**
 * Class CliBulkIndex
 * This class is intended to invoke indexing using the wp-cli
 * WP_Cli is the preferred method because Dashboard indexing is ultra slow and can produce browser interruptions/crashes
 *
 * @package MOJElasticSearch
 */
class CliBulkIndex
{
    public function __construct()
    {
        $this->output();
    }

    public function output()
    {
        //echo '<pre>'  . print_r('I am coming from output in CliBulkIndex', true) . '</pre>';
    }
}
