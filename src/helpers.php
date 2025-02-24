<?php

namespace Kantui;

use PhpTui\Term\Actions;
use PhpTui\Term\Terminal;
use Symfony\Component\VarDumper\VarDumper;

if (! function_exists('kantui_path')) {
    /**
     * Get the path to the kantui folder.
     *
     * @param  string  $path
     */
    function kantui_path($path = ''): string
    {

        $path = trim($path, '/');

        if (getenv('KANTUI_HOME')) {
            $base = getenv('KANTUI_HOME');
        } else if(function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $base = posix_getpwuid(posix_geteuid())['dir'];
            $base = "$base/.kantui";
        } else if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
            $base = getenv('USERPROFILE');
            $base = "$base/.kantui";
        }

        $path = "$base/$path";

        return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    }
}

if (! function_exists('terminal')) {
    /**
     * Get the singleton instance of the terminal.
     */
    function terminal(): Terminal
    {
        return App::getTerminal();
    }
}

if (! function_exists('dd')) {

    /**
     * Reset terminal state and dump and die vars.
     */
    function dd(mixed ...$vars): void
    {
        $terminal = terminal();
        $terminal->disableRawMode();
        $terminal->execute(Actions::cursorShow());
        $terminal->execute(Actions::alternateScreenDisable());
        $terminal->execute(Actions::disableMouseCapture());

        if (array_key_exists(0, $vars) && count($vars) === 1) {
            VarDumper::dump($vars[0]);
        } else {
            foreach ($vars as $k => $v) {
                VarDumper::dump($v, is_int($k) ? 1 + $k : $k);
            }
        }

        exit(1);
    }
}
