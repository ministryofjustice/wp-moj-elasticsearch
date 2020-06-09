<?php

namespace MOJElasticSearch;

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
        $function = self::green($function . "()");
        $info = "----------------------------------------------\n";
        $info .= "<em>Called in function " . $function . ", " . $line . "</span>\nType: " . gettype($var) . "</em>";
        $info .= "\n-------\n\n";

        return "<pre>" . $info . print_r($heading, true) . "\n\n" . print_r($var, true) . "\n\n</pre>";
    }

    /**
     * Wrap a text string in HTML to display in prominent green.
     *
     * @param string $text
     * @return string
     */
    private function green($text)
    {
        return '<span style="color:green;font-weight: bold">' . $text . "</span>";
    }
}
