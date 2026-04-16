<?php

namespace Kantui\Widgets;

use Kantui\App;
use Kantui\Contracts\AppWidget;
use Kantui\Widgets\Concerns\RendersAsDialog;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Tui\Style\Style;

/**
 * Base class for modal overlay widgets (e.g. help, detail views).
 *
 * Owns the shared $style/$app properties, ESC-to-close handling, the
 * char-key close pattern, and the empty footer. Subclasses implement
 * render() and declare which character key(s) dismiss the overlay.
 */
abstract class OverlayWidget implements AppWidget
{
    use RendersAsDialog;

    protected Style $style;

    protected ?App $app;

    public function __construct(?Style $style = null, ?App $app = null)
    {
        $this->style = $style ?? Style::default();
        $this->app = $app;
    }

    /**
     * Character key(s) that close this overlay (in addition to ESC).
     *
     * @return string[]
     */
    abstract protected function closeKeys(): array;

    /**
     * {@inheritdoc}
     */
    public function getFooterText(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function handleCharKey(CharKeyEvent $event): callable|false|null
    {
        if ($event->modifiers !== KeyModifiers::NONE) {
            return null;
        }

        if (in_array($event->char, $this->closeKeys(), true)) {
            $this->app?->returnToMainWidget();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function handleCodedKey(CodedKeyEvent $event): callable|false|null
    {
        if ($event->code == KeyCode::Esc) {
            $this->app?->returnToMainWidget();
        }

        return false;
    }
}
