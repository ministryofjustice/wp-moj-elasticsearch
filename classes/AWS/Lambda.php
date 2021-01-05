<?php

namespace MOJElasticSearch\AWS;

use MOJElasticSearch\Admin;
use MOJElasticSearch\Settings\Page;

class Lambda extends Page
{
    /**
     * @var string
     */
    public $path_data_store_js = '../assets/js/aws-lambda-store-data.js';

    /**
     * @var string
     */
    public $lf_name = "";

    /**
     * @var Admin
     */
    private $admin;

    public function __construct(Admin $admin)
    {
        parent::__construct();
        $this->admin = $admin;

        $this->lf_name = $this->getFunctionName();
    }

    public function make()
    {
        // TODO: this method should create the lambda function in AWS
        // include an administration page in the dashboard for this
        /**
         * https://docs.aws.amazon.com/cli/latest/reference/lambda/create-function.html#synopsis
         *
         */
    }

    public function makeRole()
    {

    }

    private function getFunctionName()
    {
        $base_name = '-es-write-to-s3';
        $server = env("SERVER_NAME");

        return str_replace(['.', 'docker', 'gov', 'uk', 'org', 'gov.uk'], ['-', ''], $server) . $base_name;
    }
}
