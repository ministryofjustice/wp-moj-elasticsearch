<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch\Settings;

use MOJElasticSearch\Options;
use MOJElasticSearch\Traits\Debug;
use MOJElasticSearch\Traits\Settings;

class Page extends Options
{
    use Debug, Settings;

    public $text_domain = 'wp-moj-elasticsearch';
    public static $tabs = [];
    public static $sections = [];
    public $settings_registered = false;

    public function __construct()
    {
        parent::__construct();
        $this->hooks();
    }

    /**
     * A place for all class specific hooks and filters
     */
    public function hooks()
    {
        // page set up
        add_action('admin_init', [$this, 'register']);
        add_action('admin_menu', [$this, 'settingsPage']);
        add_action('admin_menu', [$this, 'pageSettings'], 1);
    }

    /**
     * Registers a setting when createSections() is called first time
     * The register call is singular for the whole plugin
     */
    public function register()
    {
        if (!$this->settings_registered) {
            register_setting(
                $this->optionGroup(),
                $this->optionName(),
                ['sanitize_callback' => [$this, 'sanitizeSettings']]
            );
            $this->settings_registered = true;
        }
    }

    /**
     * Create the options page for the plugin
     */
    public function settingsPage()
    {
        add_options_page(
            'MoJ ES',
            'MoJ ES',
            'manage_options',
            'moj-es',
            [$this, 'init']
        );
    }

    /**
     * Set up the plugin options page
     */
    public function init()
    {
        add_thickbox();

        echo '<form action="options.php" method="post" class="moj-es" enctype="multipart/form-data">';

        // Title section
        $title = __('MoJ ES', $this->text_domain);
        $title_admin = __('Extending functionality of the ElasticPress plugin', $this->text_domain);
        echo '<h1>' . $title . ' <small class="sub-title">.' . $title_admin . '</small></h1>';

        settings_errors();

        // output tab buttons
        $this->tabs();

        // drop sections
        settings_fields($this->optionGroup());
        $this->sections();

        // drop button; update all text, check and process uploads, if required.
        submit_button('Update Settings');

        echo '</form>';
    }

    /**
     * Generates page tabs for each registered module.
     * Uses the $tabs array ~ defined by modules using sections and fields.
     */
    private function tabs()
    {
        echo '<div class="nav-tab-wrapper">';
        foreach (self::$tabs as $tab => $label) {
            echo '<a href="#moj-es-' . $tab . '" class="nav-tab">' . $label . '</a>';
        }
        echo '</div>';
    }

    /**
     * Creates the Dashboard front-end section view in our settings page.
     * Uses the $sections configuration array
     */
    private function sections()
    {
        foreach (self::$sections as $section_group_id => $sections) {
            echo '<div id="moj-es-' . $section_group_id . '" class="moj-es-settings-group">';
            foreach ($sections as $section) {
                echo '<div id="moj-es-' . $section_group_id . '" class="moj-es-settings-section">';
                echo "<h2>" . ($section['title'] ?? '') . "</h2>\n";

                if ($section['callback']) {
                    call_user_func($section['callback'], $section);
                }

                echo '<table class="form-table" role="presentation">';
                do_settings_fields($this->optionGroup(), $this->prefix . '_' . $section['id']);
                echo '</table>';

                echo '</div>';
            }
            echo '</div>';
        }
        echo '<hr/>';
    }

    /**
     * @param $section_callback array callback in array format [$this, 'mySectionIntroCallback']
     * @param $fields array of callbacks in array format with keys ['my_field_title' => [$this, 'myFieldCallback']]
     * @return array
     */
    public function section($section_callback, $fields): array
    {
        $structured_fields = [];
        foreach ($fields as $field_id => $field_callback) {
            $structured_fields[$field_id] = [
                'title' => $this->toFieldTitle($field_id),
                'callback' => $field_callback
            ];
        }

        return [
            'id' => strtolower($section_callback[1]),
            'title' => ucwords(str_replace('Intro', '', $this->camelCaseToWords($section_callback[1]))),
            'callback' => $section_callback,
            'fields' => $structured_fields
        ];
    }

    /**
     * Utility:
     * Takes a string in the form of camelCase or CamelCase and splits to individual words
     * @param $string
     * @return string
     * @example theQuickBrownFox = the Quick Brown Fox
     * @example TheQuickBrownFox = The Quick Brown Fox
     */
    public function camelCaseToWords($string)
    {
        $regex = '/
              (?<=[a-z])      # Position is after a lowercase,
              (?=[A-Z])       # and before an uppercase letter.
            | (?<=[A-Z])      # Or g2of2; Position is after uppercase,
              (?=[A-Z][a-z])  # and before upper-then-lower case.
            /x';
        $words = preg_split($regex, $string);
        $count = count($words);
        if (is_array($words)) {
            $string = '';
            for ($i = 0; $i < $count; ++$i) {
                $string .= $this->toWord($words[$i]);
            }
        }

        return rtrim($string);
    }

    private function toFieldTitle($text)
    {
        $text = ucwords(str_replace(['-', '_'], ' ', $text));
        $words = explode(' ', $text);
        $title = '';
        foreach ($words as $word) {
            $title .= $this->toWord($word) . ' ';
        }
        return $title;
    }

    private function toWord($word)
    {
        switch ($word) {
            case 'MOJ':
                $word = 'MoJ';
                break;
            case 'A':
            case 'Of':
            case 'Is':
                $word = strtolower($word);
                break;
            case 'Wp':
            case 'Db':
                $word = strtoupper($word);
                break;
            case 'Colon':
                $word = ':';
                break;
        }

        return $word . " ";
    }

    /**
     * This method is quite literally a space saving settings method
     *
     * Create your tab by adding to the $tabs global array with a label as the value
     * Configure a section with fields for that tab as arrays by adding to the $sections global array.
     *
     * @SuppressWarnings(PHPMD)
     */
    public function pageSettings()
    {
        // define section (group) and tabs
        $group = 'home';
        Page::$tabs[$group] = 'Welcome';

        // define fields
        $fields_intro = [
            'introduction' => [$this, 'homeIntroduction']
        ];

        $fields_credits = [
            'credits' => [$this, 'teamCredits']
        ];

        // fill the sections
        Page::$sections[$group] = [
            $this->section([$this, 'guidanceColonWpMOJElasticsearch'], [])
        ];

        $this->createSections($group);
    }

    public function guidanceColonWpMOJElasticsearch()
    {
        $heading = __('Welcome to the settings console for interacting with AWS Elasticsearch', $this->text_domain);
        $description = __(
            'This plugin has been created to interface directly with the popular plugin ElasticPress,
            provided by the company 10up',
            $this->text_domain
        );

        echo '<div class="intro"><strong>' . $heading . '</strong><br>' . $description . '</div>
            <h3>Enhancements and Features</h3>
            <h4>Enhancements</h4>
            <ul>
                <li>Enhancement here</li>
                <li>Enhancement here</li>
            </ul>
            <h4>Features</h4>
            <ul>
                <li>Feature here</li>
                <li>Feature here</li>
            </ul>';
    }

    public function homeIntroduction()
    {
        echo '';
    }

    public function teamCredits()
    {
        echo '';
    }
}
