<?php

namespace MOJElasticSearch;

/**
 * Class Authentication
 * @package MOJElasticSearch
 */
trait Auth
{
    public static function auth()
    {
        // is the WP environment loaded?
        self::_hasAbsPath();

        // middleware check
        add_action('init', ['\MOJElasticSearch\Auth', 'check']);
    }

    public static function check()
    {
        // is WP environment running?
        self::_canRunEnvironment();
        // is ElasticPress present and activated?
        self::_hasElasticPress();
        // user is authorised
        self::canView();
    }

    /**
     * Checks to see if we have the ABSPATH constant and exits the application if not
     * A default way to check if the WordPress environment is available
     */
    private static function _hasAbsPath()
    {
        if (!defined('ABSPATH')) {
            self::error('forbidden');
        }
    }

    /**
     * Makes sure WordPress can operate properly before launching the plugin
     */
    private static function _canRunEnvironment()
    {
        if (!function_exists('add_action')) {
            self::error('unavailable');
        }
    }

    /**
     * Run a constant check on the installation status of ElasticPress
     */
    private static function _hasElasticPress()
    {
        // check if ElasticPress available
        if (!defined('EP_VERSION')) {
            self::error('unavailable');
        }
    }

    public static function canView()
    {
        /** Check permissions. */
        if (!is_admin() || !current_user_can('manage_options')) {
            self::error('forbidden');
        }
    }

    /**
     * Error helper for private exit methods
     * @param String $type unauthorised | forbidden | unavailable
     */
    public static function error(String $type)
    {
        call_user_func(['\MOJElasticSearch\Auth', $type]);
    }

    public static function unauthorised()
    {
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }

    public static function forbidden()
    {
        header("HTTP/1.1 403 Forbidden");
        exit;
    }

    public static function unavailable()
    {
        header("HTTP/1.1 503 Service Temporarily Unavailable");
        exit;
    }
}
