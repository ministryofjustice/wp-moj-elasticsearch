<?php

namespace MOJElasticSearch;

trait Debug
{
    /**
     * Define a heading and pass through a variable to display output
     *
     * @uses DEBUG_ECHO
     * @param string $heading
     * @param mixed $var
     * @param bool $die
     * @return string|null
     */
    public static function this(string $heading, $var, $die = false)
    {
        $bt = debug_backtrace();
        $function = $bt[2]['function'];
        $caller = array_shift($bt);

        $line = self::_green("line " . $caller['line']);
        $function = self::_green($function . "()");
        $info = "----------------------------------------------\n";
        $info .= "<em>Called in function " . $function . ", " . $line . "</span>\nType: " . gettype($var) . "</em>";
        $info .= "\n-------\n\n";

        $output = "<pre>" . $info . print_r($heading, true) . "\n\n" . print_r($var, true) . "\n\n</pre>";

        if (defined('DEBUG_ECHO') && DEBUG_ECHO === true) {
            echo $output;
            if ($die) {
                die();
            }
            return null;
        }

        return $output;
    }

    /**
     * Wrap a text string in HTML to display in prominent green.
     *
     * @param string $text
     * @return string
     */
    private static function _green($text)
    {
        return '<span style="color:green;font-weight: bold">' . $text . "</span>";
    }
}
