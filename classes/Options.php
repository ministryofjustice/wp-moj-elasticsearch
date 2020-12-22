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

        // if not present, create the default stats file.
        if (!file_exists($this->importLocation() . 'moj-bulk-index-stats.json')) {
            $this->setStats($this->stats_default);
        }

        // finally, get from the file system
        return (array)json_decode(file_get_contents($this->importLocation() . 'moj-bulk-index-stats.json'));
    }

    public function setStats($es_stats)
    {
        $this->moj_bulk_index_stats = $es_stats;

        if ($this->stats_use_db) {
            update_option('_moj_bulk_index_stats', $es_stats);
            return $es_stats;
        }

        // storage is file system
        $handle = fopen($this->importLocation() . 'moj-bulk-index-stats.json', 'w');
        fwrite($handle, json_encode($es_stats));
        while (is_resource($handle)) {
            fclose($handle);
        }

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

        if (file_exists($this->importLocation() . 'moj-bulk-index-stats.json')) {
            unlink($this->importLocation() . 'moj-bulk-index-stats.json');
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
            $text = 'Caught location exception: ' .  $e->getMessage();
            trigger_error($text);
            $this->message($text);
        }
    }

    /**
     * Log a message for display on the front end
     * part of the stats api
     * @param $text
     * @param bool $stats
     */
    public function message($text, &$stats)
    {
        if ($this->options()['show_cleanup_messages'] ?? null) {
            $stats['messages'][] = $text;
        }
    }
}
