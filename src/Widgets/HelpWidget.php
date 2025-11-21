<?php

namespace Kantui\Widgets;

use Kantui\App;
use Kantui\Contracts\AppWidget;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\HorizontalAlignment;
use PhpTui\Tui\Widget\Widget;

class HelpWidget implements AppWidget
{
    // Navigation bindings
    public const MOVE_DOWN = 'j or ↓';

    public const MOVE_UP = 'k or ↑';

    public const MOVE_LEFT = 'h or ←';

    public const MOVE_RIGHT = 'l or →';

    // Item action bindings
    public const PROGRESS_ITEM = 'ENTER';

    public const REGRESS_ITEM = 'BACKSPACE';

    public const NEW_ITEM = 'n';

    public const EDIT_ITEM = 'e';

    public const DELETE_ITEM = 'x';

    public const VIEW_DETAILS = 'i';

    // Reordering bindings
    public const MOVE_ITEM_UP = '[';

    public const MOVE_ITEM_DOWN = ']';

    // Filter/Search bindings
    public const SEARCH = '/';

    public const FILTER_URGENCY = 'f';

    public const CLEAR_FILTERS = 'c';

    // Application bindings
    public const TOGGLE_HELP = '?';

    public const QUIT = 'q';

    private bool $showReorderBindings;

    private Style $style;

    private ?App $app;

    public function __construct(bool $showReorderBindings = true, ?Style $style = null, ?App $app = null)
    {
        $this->showReorderBindings = $showReorderBindings;
        $this->style = $style ?? Style::default();
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function render(): Widget
    {
        $help = $this->buildHelpText();

        $helpBlock = BlockWidget::default()
            ->borders(Borders::ALL)
            ->titles(Title::fromString('Available Keybindings Help - Press ? or ESC to close'))
            ->style($this->style)
            ->widget(
                ParagraphWidget::fromText(
                    Text::fromString($help)
                )->alignment(HorizontalAlignment::Left)
            );

        // Center the help dialog with margins on both sides
        return GridWidget::default()
            ->direction(Direction::Horizontal)
            ->constraints(
                Constraint::percentage(10),  // Left margin
                Constraint::percentage(80),  // Help content
                Constraint::percentage(10)   // Right margin
            )
            ->widgets(
                BlockWidget::default(),  // Empty left block
                $helpBlock,
                BlockWidget::default()   // Empty right block
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getFooterText(): string
    {
        // No footer text for this widget, "q" is the only binding, we can put up by title.
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

        // Close help on q or ?
        if ($event->char === 'q' || $event->char === '?') {
            if ($this->app !== null) {
                $this->app->returnToMainWidget();
            }
        }

        return false; // Continue event loop
    }

    /**
     * {@inheritdoc}
     */
    public function handleCodedKey(CodedKeyEvent $event): callable|false|null
    {
        // Close help on ESC
        if ($event->code == KeyCode::Esc) {
            if ($this->app !== null) {
                $this->app->returnToMainWidget();
            }
        }

        return false; // Continue event loop
    }

    /**
     * Build the help text content.
     */
    private function buildHelpText(): string
    {
        $sections = [
            'NAVIGATION' => [
                self::MOVE_DOWN => 'Move cursor down',
                self::MOVE_UP => 'Move cursor up',
                self::MOVE_LEFT => 'Move to previous column/status',
                self::MOVE_RIGHT => 'Move to next column/status',
            ],
            'ITEM ACTIONS' => [
                self::PROGRESS_ITEM => 'Progress item to next status',
                self::REGRESS_ITEM => 'Move item back to previous status',
                self::NEW_ITEM => 'Create new todo item',
                self::EDIT_ITEM => 'Edit selected item',
                self::DELETE_ITEM => 'Delete selected item',
                self::VIEW_DETAILS => 'View full todo details',
            ],
            'FILTERING & SEARCH' => [
                self::SEARCH => 'Search/filter todos',
                self::FILTER_URGENCY => 'Filter by urgency',
                self::CLEAR_FILTERS => 'Clear all active filters',
            ],
            'APPLICATION' => [
                self::TOGGLE_HELP => 'Toggle this help dialog',
                self::QUIT => 'Quit application/Close help dialog',
            ],
        ];

        // Add reordering section only if applicable
        if ($this->showReorderBindings) {
            $sections['ITEM REORDERING'] = [
                self::MOVE_ITEM_UP => 'Move item up in list',
                self::MOVE_ITEM_DOWN => 'Move item down in list',
            ];
        }

        $lines = [];
        $lines[] = '';  // Top padding

        foreach ($sections as $sectionName => $bindings) {
            $lines[] = "  $sectionName";
            $lines[] = '  '.str_repeat('─', 50);

            foreach ($bindings as $key => $description) {
                $lines[] = sprintf('    %-15s  %s', $key, $description);
            }

            $lines[] = '';  // Section spacing
        }

        return implode("\n", $lines);
    }
}
