<?php

namespace MOJElasticSearch\Traits;

trait Debug
{
    /**
     * Define a heading and pass through a variable to display output
     *
     * @param string $heading
     * @param mixed $var
     * @return string|null
     */
    public function debug(string $heading, $var)
    {
        $backtrace = debug_backtrace();
        $function = $backtrace[2]['function'];
        $caller = array_shift($backtrace);

        $line = self::green("line " . $caller['line']);
        $function = self::blue($function . "()");
        $info = "Called in function " . $function . ", " . $line . "\nType: " . self::orange(gettype($var));
        $info .= "\n" . self::grey("-------") . "\n\n";

        return self::pre($info . print_r(self::head($heading), true) .
            "\n\n" . self::code(print_r($var, true)) . "\n\n");
    }

    /**
     * Wrap a text string in HTML to display in prominent green.
     *
     * @param string $text
     * @return string
     */
    private function green($text)
    {
        if (defined('WP_CLI') && WP_CLI == true) {
            return "\033[0;32m" . $text . "\033[0m";
        }

        return '<span style="color:green;font-weight: bold">' . $text . "</span>";
    }

    /**
     * Wrap a text string in HTML to display in prominent red.
     *
     * @param string $text
     * @return string
     */
    private function head($text)
    {
        if (defined('WP_CLI') && WP_CLI == true) {
            return "\033[1;37m" . $text . "\033[0m";
        }

        return '<span style="color:#cc0000;font-weight: bold;font-size: 1.5em">' . $text . "</span>";
    }

    /**
     * Wrap a text string in HTML to display in prominent blue.
     *
     * @param string $text
     * @return string
     */
    private function blue($text)
    {
        if (defined('WP_CLI') && WP_CLI == true) {
            return "\033[0;32m" . $text . "\033[0m";
        }

        return '<span style="color:#0e2fc1;font-weight: bold">' . $text . "</span>";
    }

    /**
     * Wrap a text string in HTML to display in prominent orange.
     *
     * @param string $text
     * @return string
     */
    private function orange($text)
    {
        if (defined('WP_CLI') && WP_CLI == true) {
            return "\033[0;33m" . $text . "\033[0m";
        }

        return '<span style="color:orangered;font-size: 1.2em">' . $text . "</span>";
    }

    /**
     * Wrap a text string in HTML to display in prominent gray.
     *
     * @param string $text
     * @return string
     */
    private function grey($text)
    {
        if (defined('WP_CLI') && WP_CLI == true) {
            return "\033[2m" . $text . "\033[0m";
        }

        return '<span style="color:#bbbbbb;font-weight: bold">' . $text . "</span>";
    }

    /**
     * Wrap a text string in styled <pre>.
     *
     * @param string $text
     * @return string
     */
    private function pre($text)
    {
        if (defined('WP_CLI') && WP_CLI == true) {
            return "\n\033[1;2mD E B U G G I N G ...\033[0m\n\n" . $text;
        }

        $styles = [
            'white-space: pre-wrap',
            'background: #e2e2e2',
            'padding: 16px 20px',
            'border: 1px solid #bfbfbf',
            'border-radius: 4px;'
        ];
        return '<pre style="' . implode(';', $styles) . '">' . $text . "</pre>";
    }

    /**
     * Wrap a text string in styled <code>.
     *
     * @param string $text
     * @return string
     */
    private function code($text)
    {
        if (defined('WP_CLI') && WP_CLI == true) {
            return "\033[0;32m" . $text . "\033[0m\033[2m\n--------------------------------\033[0m";
        }

        $styles = [
            'background: #f1f1f1',
            'padding: 10px 20px',
            'display: inline-block',
            'border: 1px solid #bfbfbf',
            'border-radius: 4px'
        ];
        return '<code style="' . implode(';', $styles) . '">' . $text . "</code>";
    }
}
