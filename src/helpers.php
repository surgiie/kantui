<?php

namespace Kantui;

use PhpTui\Term\Terminal;
use PhpTui\Tui\Style\Style;
use Symfony\Component\VarDumper\VarDumper;

if (! function_exists('kantui_path')) {
    /**
     * Get the path to the kantui folder.
     *
     * Constructs an absolute path to a resource within the kantui home directory.
     * The home directory is determined by KANTUI_HOME environment variable,
     * or defaults to ~/.kantui in the user's home directory.
     *
     * @param  string  $path  The relative path within the kantui directory
     * @return string The absolute path with proper directory separators
     */
    function kantui_path($path = ''): string
    {

        $path = trim($path, '/');

        if (getenv('KANTUI_HOME')) {
            $base = getenv('KANTUI_HOME');
        } elseif (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $base = posix_getpwuid(posix_geteuid())['dir'];
            $base = "$base/.kantui";
        } elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $base = getenv('USERPROFILE');
            $base = "$base/.kantui";
        }

        $path = "$base/$path";

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}

if (! function_exists('terminal')) {
    /**
     * Get the singleton instance of the terminal.
     *
     * Returns the global Terminal instance used by the application.
     *
     * @return Terminal The terminal instance
     */
    function terminal(): Terminal
    {
        return App::getTerminal();
    }
}

if (! function_exists('default_style')) {
    /**
     * Get the default white style used throughout the application.
     *
     * Returns a white foreground color style for consistent text rendering.
     *
     * @return Style The default white style
     */
    function default_style(): Style
    {
        return Style::default()->white();
    }
}

if (! function_exists('dd')) {

    /**
     * Reset terminal state and dump and die.
     *
     * Cleans up the terminal state before dumping variable(s) and exiting.
     * This prevents the terminal from being left in a broken state.
     *
     * @param  mixed  ...$vars  Variables to dump
     * @return never This function never returns (exits with code 1)
     */
    function dd(mixed ...$vars): never
    {
        App::cleanupTerminal();

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
