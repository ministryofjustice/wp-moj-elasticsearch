<?php

namespace MOJElasticSearch;

/**
 * Class Authentication
 * @package MOJElasticSearch
 */
class Auth
{
    public function __construct()
    {
        // is the WP environment loaded?
        $this->hasAbsPath();
        $this->hooks();
    }

    public function hooks()
    {
        // middleware check
        add_action('init', [$this, 'check']);
    }

    public function check()
    {
        // is WP environment running?
        $this->canRunEnvironment();
        // is ElasticPress present and activated?
        $this->hasElasticPress();
        // user is authorised
        $this->canView();
    }

    /**
     * Checks to see if we have the ABSPATH constant and exits the application if not
     * A default way to check if the WordPress environment is available
     */
    private function hasAbsPath()
    {
        if (!defined('ABSPATH')) {
            $this->error('forbidden');
        }
    }

    /**
     * Makes sure WordPress can operate properly before launching the plugin
     */
    private function canRunEnvironment()
    {
        if (!function_exists('add_action')) {
            $this->error('unavailable');
        }
    }

    /**
     * Run a constant check on the installation status of ElasticPress
     */
    private function hasElasticPress()
    {
        // check if ElasticPress available
        if (!defined('EP_VERSION')) {
            $this->error('unavailable');
        }
    }

    /**
     * Early-on user permission check
     */
    public function canView()
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            $_get = $_GET['page'];
            if ($_get === 'moj_es') {
                $this->error('forbidden');
            }
        }
    }

    /**
     * Error helper for private exit methods
     * @param String $type unauthorised | forbidden | unavailable
     * @return mixed
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function error(String $type)
    {
        call_user_func([$this, $type]);
        exit;
    }

    public function unauthorised()
    {
        header("HTTP/1.1 401 Unauthorized");
    }

    public function forbidden()
    {
        header("HTTP/1.1 403 Forbidden");
    }

    public function unavailable()
    {
        header("HTTP/1.1 503 Service Temporarily Unavailable");
    }
}
