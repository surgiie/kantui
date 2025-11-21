<?php

namespace Kantui\Contracts;

use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Tui\Widget\Widget;

/**
 * Interface for application widgets that can be rendered and handle input events.
 */
interface AppWidget
{
    /**
     * Render the widget.
     *
     * @return Widget The PhpTui widget to be rendered
     */
    public function render(): Widget;

    /**
     * Get the footer text to display at the bottom of the screen.
     *
     * @return string The footer help text
     */
    public function getFooterText(): string;

    /**
     * Handle character key events (a-z, 0-9, symbols, etc.).
     *
     * @param  CharKeyEvent  $event  The character key event
     * @return callable|false|null
     *                             - null: Signal to quit the application
     *                             - callable: A function to execute (may restart the app)
     *                             - false: Continue the event loop
     */
    public function handleCharKey(CharKeyEvent $event): callable|false|null;

    /**
     * Handle coded key events (arrows, enter, escape, etc.).
     *
     * @param  CodedKeyEvent  $event  The coded key event
     * @return callable|false|null
     *                             - null: Signal to quit the application
     *                             - callable: A function to execute (may restart the app)
     *                             - false: Continue the event loop
     */
    public function handleCodedKey(CodedKeyEvent $event): callable|false|null;
}
