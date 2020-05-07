<?php

namespace MOJElasticSearch;

trait Debug
{
    public static function this($heading, $var, $die = false)
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
        if (defined('DEBUG_ECHO')) {
            echo $output;
            if ($die) {
                die();
            }

            return null;
        }

        return $output;
    }

    private static function _green($text)
    {
        return '<span style="color:green;font-weight: bold">' . $text . "</span>";
    }
}
