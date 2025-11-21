<?php

namespace Kantui;

use Kantui\Contracts\AppWidget;
use Kantui\Support\Context;
use Kantui\Support\Cursor;
use Kantui\Support\DataManager;
use Kantui\Support\Enums\TodoType;
use Kantui\Widgets\MainWidget;
use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\Event\MouseEvent;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Bridge\PhpTerm\PhpTermBackend as PhpTuiPhpTermBackend;
use PhpTui\Tui\Display\Display;
use PhpTui\Tui\DisplayBuilder;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\HorizontalAlignment;
use PhpTui\Tui\Widget\Widget;
use Throwable;

use function Amp\delay;
use function Laravel\Prompts\clear;

class App
{
    /**
     * Layout percentage constants.
     */
    private const LAYOUT_MAIN_PERCENTAGE = 99;

    private const LAYOUT_FOOTER_PERCENTAGE = 1;

    /**
     * The app instance.
     */
    protected static App $app;

    /**
     * The terminal instance.
     */
    protected static Terminal $terminal;

    /**
     * The display/drawer instance.
     */
    protected Display $display;

    /**
     * The todo data manager.
     */
    protected DataManager $manager;

    /**
     * The currently active widget.
     */
    protected AppWidget $activeWidget;

    /**
     * The main widget instance (saved for returning from help).
     */
    protected ?MainWidget $mainWidget = null;

    /**
     * Initial cursor and active type for app state.
     */
    protected Cursor $cursor;

    protected TodoType $activeType;

    public function __construct(
        protected Context $context,
        protected string $version,
        ?Cursor $cursor = null,
        ?TodoType $activeType = null
    ) {
        static::$terminal = isset(static::$terminal) ? static::$terminal : Terminal::new();

        $this->display = DisplayBuilder::default(
            PhpTuiPhpTermBackend::new(static::$terminal)
        )->fullscreen()->build();

        $this->cursor = $cursor ?? new Cursor(0, Cursor::INITIAL_PAGE);
        $this->activeType = $activeType ?? TodoType::TODO;

        static::$app = $this;
    }

    /**
     * Get the application instance.
     */
    public static function getInstance(): App
    {
        if (! isset(static::$app)) {
            throw new \RuntimeException('App instance not set.');
        }

        return static::$app;
    }

    /**
     * Get the terminal instance.
     */
    public static function getTerminal(): Terminal
    {
        if (! isset(static::$terminal)) {
            throw new \RuntimeException('Terminal instance not set.');
        }

        return static::$terminal;
    }

    /**
     * Cleanup and reset terminal to normal state.
     */
    public static function cleanupTerminal(): void
    {
        if (! isset(static::$terminal)) {
            return;
        }

        try {
            static::$terminal->disableRawMode();
            static::$terminal->execute(Actions::cursorShow());
            static::$terminal->execute(Actions::alternateScreenDisable());
            static::$terminal->execute(Actions::disableMouseCapture());
        } catch (Throwable) {
            // Ignore errors during cleanup
        }
    }

    /**
     * Set the active widget.
     */
    public function setActiveWidget(AppWidget $widget): void
    {
        $this->activeWidget = $widget;
    }

    /**
     * Return to the main widget from another widget (e.g., help).
     */
    public function returnToMainWidget(): void
    {
        if ($this->mainWidget !== null) {
            $this->activeWidget = $this->mainWidget;
        }
    }

    /**
     * Run the application.
     */
    public function run(): int
    {
        $this->manager = new DataManager($this->context);

        // Initialize the main widget (this is the main todo/in progress home view)
        $this->mainWidget = new MainWidget(
            $this->manager,
            $this->context,
            $this->version,
            $this->cursor,
            $this->activeType
        );

        $this->setActiveWidget($this->mainWidget);

        try {
            static::$terminal->execute(Actions::cursorHide());
            static::$terminal->execute(Actions::alternateScreenEnable());
            static::$terminal->execute(Actions::enableMouseCapture());
            static::$terminal->enableRawMode();

            return $this->start();
        } catch (Throwable $err) {
            static::cleanupTerminal();
            static::$terminal->execute(Actions::clear(ClearType::All));
            throw $err;
        }
    }

    /**
     * Start and render the TUI application and listen for events.
     */
    protected function start(): int
    {
        $action = null;

        while (true) {
            while (null != ($event = static::$terminal->events()->next())) {
                $result = null;

                if ($event instanceof CharKeyEvent) {
                    $result = $this->activeWidget->handleCharKey($event);
                }

                if ($event instanceof CodedKeyEvent) {
                    $result = $this->activeWidget->handleCodedKey($event);
                }

                if ($event instanceof MouseEvent) {
                    // Ignore mouse events
                    $result = false;
                }

                // Handle the result
                if ($result === null) {
                    break 2; // Quit
                }

                if (is_callable($result)) {
                    $action = $result;
                    break 2;
                }
            }

            $this->display->draw($this->buildLayout());
            delay(0.01);
        }

        static::cleanupTerminal();

        if (is_null($action)) {
            return 0;
        }

        $result = $action();
        $action = null;

        return 0;
    }

    /**
     * Build the main layout with widget and footer.
     */
    protected function buildLayout(): Widget
    {
        return GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(
                Constraint::percentage(self::LAYOUT_MAIN_PERCENTAGE),
                Constraint::percentage(self::LAYOUT_FOOTER_PERCENTAGE)
            )
            ->widgets(
                $this->activeWidget->render(),
                ParagraphWidget::fromText(
                    Text::fromString($this->activeWidget->getFooterText())
                )->alignment(HorizontalAlignment::Right)->style(\Kantui\default_style())
            );
    }

    /**
     * Restart the app with new cursor position.
     */
    public function restartApp(?TodoType $type = null, ?Cursor $cursor = null): int
    {
        $app = new self($this->context, $this->version, $cursor, $type);

        return $app->run();
    }
}
