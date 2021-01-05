<?php

namespace MOJElasticSearch;

use Exception;

class Options
{
    /**
     * @var string prefix
     */
    public $prefix = 'moj_es';

    /**
     * @var null options > do not access directly, use $this->options()
     */
    protected $options = null;

    /**
     * @var int options
     */
    public $options_timer = 0;

    /**
     * @var string
     */
    public $option_name = '_settings';

    /**
     * @var string
     */
    public $option_group = '_plugin';

    /**
     * @var null
     */
    public $moj_bulk_index_stats = null;

    /**
     * @var boolean
     */
    public $stats_use_db = false;

    /**
     * @var array
     */
    public $stats = [];

    /**
     * @var array
     */
    public $stats_default = [
        'total_bulk_requests' => 0,
        'total_stored_requests' => 0,
        'total_large_requests' => 0,
        'bulk_body_size' => 0,
        'bulk_body_size_bytes' => 0, // isn't used on front end display
        'large_files' => [],
        'messages' => [],
        'cleanup_loops' => 0,
        'force_stop' => false
    ];

    public function __construct()
    {
        $this->stats_use_db = $this->options()['storage_is_db'] ?? false;
        $this->options_timer = time();
    }

    /**
     * Simple wrapper to fetch the plugins data array
     * @return mixed|void
     * @uses get_option()
     */
    public function options()
    {
        $this->optionsTimer();

        if (!$this->options) {
            $this->options = get_option($this->optionName(), []);
        }

        return $this->options;
    }

    /**
     * Simple wrapper to fetch the plugins data array
     * @return mixed|void
     * @uses get_option()
     */
    public function statsDB()
    {
        if (!$this->stats) {
            $this->stats = get_option('_moj_bulk_index_stats', $this->stats_default);
        }

        return $this->stats;
    }

    /**
     * Manages the refresh rate of cached plugin options and prevents too many DB requests
     * @return bool
     */
    private function optionsTimer()
    {
        // refresh options array after 5 seconds
        if (time() - $this->options_timer > 5) {
            $this->options_timer = time();
            $this->options = null;
            return true;
        }

        return false;
    }

    /**
     * Get the option group for the plugin settings. Used as 'page' in register_settings()
     * @return string
     */
    public function optionGroup()
    {
        return $this->prefix . $this->option_group;
    }

    /**
     * The settings name for our plugins option data.
     * Calling get_option() with this string will produce the plugins data.
     * @return string
     */
    public function optionName()
    {
        return $this->prefix . $this->option_name;
    }

    /**
     * Update a setting value using the WP Settings API
     * @param $key
     * @param $value
     * @return bool
     */
    public function updateOption($key, $value)
    {
        $options = $this->options();

        $options[$key] = $value;

        if ($value === null) {
            unset($options[$key]);
        }

        return update_option($this->optionName(), $options);
    }

    /**
     * Delete a setting value using the WP Settings API
     * @param $key
     * @return bool
     */
    public function deleteOption($key)
    {
        $options = $this->options();

        unset($options[$key]);
        return update_option($this->optionName(), $options);
    }

    /**
     * Get the stats stored in options or from disc
     * @return array|string|null
     */
    public function getStats()
    {
        if ($this->moj_bulk_index_stats) {
            return $this->moj_bulk_index_stats;
        }

        if ($this->stats_use_db) {
            return $this->statsDB();
        }

        $file_path = $this->importLocation() . 'moj-bulk-index-stats.json';
        // if not present, create the default stats file.
        clearstatcache(true, $file_path);
        if (!file_exists($file_path)) {
            $this->setStats($this->stats_default);
        }

        // finally, get from the file system
        return (array)json_decode(file_get_contents($file_path));
    }

    public function setStats($es_stats)
    {
        $this->moj_bulk_index_stats = $es_stats;

        if ($this->stats_use_db) {
            update_option('_moj_bulk_index_stats', $es_stats);
            return $es_stats;
        }

        // storage is file system
        $file_path = $this->importLocation() . 'moj-bulk-index-stats.json';
        $handle = fopen($file_path, 'w');
        fwrite($handle, json_encode($es_stats));
        fclose($handle);

        // touch to adjust modification time.
        touch($file_path);

        return true;
    }

    /**
     * @return bool if stats were cleared
     */
    public function clearStats()
    {
        if ($this->stats_use_db) {
            $this->moj_bulk_index_stats = null;
            return update_option('_moj_bulk_index_stats', $this->stats_default);
        }

        $file_path = $this->importLocation() . 'moj-bulk-index-stats.json';
        clearstatcache(true, $file_path);
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        return true;
    }

    /**
     * Defines the import data location in the uploads directory.
     * @return string
     * @throws Exception
     */
    private function tmpPath()
    {
        $file_dir = get_temp_dir();
        $path = $file_dir . basename(plugin_dir_path(dirname(__FILE__, 1)));

        if (!is_dir($path)) {
            if (!mkdir($path)) {
                throw new Exception('importLocation directory could not be created in : ' . $path);
            }
        }

        return trailingslashit($path);
    }

    /**
     * Return th
     * @return string
     */
    public function importLocation()
    {
        try {
            return $this->tmpPath();
        } catch (Exception $e) {
            trigger_error('Caught location exception: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Log a message for display on the front end
     * part of the stats api
     * @param $text
     * @param array $stats
     */
    public function message($text, &$stats)
    {
        if ($this->options()['show_cleanup_messages'] ?? null) {
            $stats['messages'][] = $text;
        }
    }

    /**
     * Reset the messages array
     * part of the stats api
     * @param $stats
     */
    public function messageReset(&$stats)
    {
        $stats['messages'] = [];
    }
}
