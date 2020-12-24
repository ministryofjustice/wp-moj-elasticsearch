<?php

namespace MOJElasticSearch;

/**
 * Class Authentication
 * @package MOJElasticSearch
 */
class Auth
{
    public $ok = true;

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
            $this->exitPlugin('unavailable');
        }
    }

    /**
     * Run a constant check on the installation status of ElasticPress
     */
    private function hasElasticPress()
    {
        // check if ElasticPress available
        if (!defined('EP_VERSION')) {
            $this->exitPlugin('PluginCantRun');
        }
    }

    /**
     * Early-on user permission check
     */
    public function canView()
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            $_get = $_GET['page'] ?? '';
            if ($_get === 'moj_es') {
                $this->exitPlugin('forbidden');
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

    /**
     * Error helper for private exit methods
     * @param String $callback
     * @return null
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function exitPlugin(String $callback)
    {
        add_action('admin_notices', [$this, 'notice' . $callback]);
    }

    public function unauthorised()
    {
        $class = 'notice notice-error is-dismissible';
        $message = __('MoJ Elasticsearch cannot run. A 401 Unauthorised was encountered.');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        $this->ok = false;
    }

    public function forbidden()
    {
        $class = 'notice notice-error is-dismissible';
        $message = __('MoJ Elasticsearch cannot run. 403 Forbidden.');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        $this->ok = false;
    }

    public function unavailable()
    {
        $class = 'notice notice-error is-dismissible';
        $message = __('MoJ Elasticsearch cannot run. A 503 Service Temporarily Unavailable was encountered.');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        $this->ok = false;
    }

    public function noticePluginCantRun()
    {
        $class = 'notice notice-error is-dismissible';
        $message = __('Whoops! MoJ Elasticsearch cannot run. Please check that ElasticPress is activated.');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        $this->ok = false;
    }
}
