<?php

namespace Kantui;

use Illuminate\Pagination\LengthAwarePaginator;
use Kantui\Support\Context;
use Kantui\Support\Cursor;
use Kantui\Support\DataManager;
use Kantui\Support\Enums\TodoType;
use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Bridge\PhpTerm\PhpTermBackend as PhpTuiPhpTermBackend;
use PhpTui\Tui\Display\Display;
use PhpTui\Tui\DisplayBuilder;
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
use Throwable;

use function Amp\delay;
use function Laravel\Prompts\clear;

class App
{
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
     * The current active type.
     */
    protected ?TodoType $activeType;

    /**
     * The cursors for the todo items.
     */
    protected array $cursors;

    /**
     * The todo data manager.
     */
    protected DataManager $manager;

    /**
     * The current page of todos.
     */
    protected LengthAwarePaginator $todos;

    /**
     * The current page of in progress todos.
     */
    protected LengthAwarePaginator $inProgress;

    public function __construct(
        protected Context $context,
        protected string $version
    ) {
        static::$terminal = isset(static::$terminal) ? static::$terminal : Terminal::new();

        $this->display = DisplayBuilder::default(
            PhpTuiPhpTermBackend::new(static::$terminal)
        )->fullscreen()->build();

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
        static::$terminal->disableRawMode();
        static::$terminal->execute(Actions::cursorShow());
        static::$terminal->execute(Actions::alternateScreenDisable());
        static::$terminal->execute(Actions::disableMouseCapture());
    }

    /** Set active type. */
    public function setActiveType(TodoType $type): void
    {
        $this->activeType = $type;
    }

    /** Set cursor for a given type. */
    public function setCursor(TodoType $type, Cursor $cursor): void
    {
        $this->cursors[$type->value] = $cursor;
    }

    /**
     * Run the application.
     */
    public function run(): int
    {
        $this->manager = new DataManager($this->context);

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

        return 0;
    }

    /** Get active items. */
    protected function getActiveItems(): ?LengthAwarePaginator
    {
        return match ($this->activeType) {
            TodoType::TODO => $this->todos,
            TodoType::IN_PROGRESS => $this->inProgress,
            default => null,
        };
    }

    /**
     * Move the cursor down.
     */
    protected function moveCursorDown(): void
    {
        $cursor = $this->getCursor();

        if (is_null($cursor)) {
            $this->activeType = TodoType::TODO;
            $cursor = $this->cursors[TodoType::TODO->value];

            if ($this->todos->count() === 0 && $this->inProgress->count() > 0) {
                $this->activeType = TodoType::IN_PROGRESS;
                $cursor = $this->cursors[TodoType::IN_PROGRESS->value];
            }
        }

        $activeItems = $this->getActiveItems();

        $nextIndex = $cursor->index() + 1;

        if ($nextIndex > ($activeItems->count() - 1) && ! $activeItems->hasMorePages()) {
            $cursor->setIndex($activeItems->count() - 1);

            return;
        }

        if ($nextIndex > ($activeItems->count() - 1) && $activeItems->hasMorePages()) {
            $cursor->setIndex(0);
            $cursor->setPage($cursor->nextPage());

            return;
        }

        if ($nextIndex < $activeItems->count()) {
            $cursor->increment();

            return;
        }

    }

    /**
     * Move the cursor up.
     */
    protected function moveCursorUp(): void
    {
        $cursor = $this->getCursor();

        if (is_null($cursor)) {
            return;
        }

        if ($cursor->index() < 0) {
            return;
        }

        $previousIndex = $cursor->index() - 1;

        if ($previousIndex < 0 && $cursor->page() > 1) {
            $cursor->setIndex(DataManager::PAGINATE_BY - 1);
            $cursor->setPage($cursor->previousPage());

            return;
        }

        if ($previousIndex < 0 && $cursor->page() === Cursor::INITIAL_PAGE) {
            $cursor->setIndex(0);
            $cursor->setPage(Cursor::INITIAL_PAGE);

            return;
        }

        $cursor->decrement();

    }

    /**
     * Move the cursor left.
     */
    protected function moveCursorLeft(): void
    {
        $cursor = $this->getCursor();

        if (is_null($cursor)) {
            return;
        }

        if ($this->todos->count() === 0) {
            return;
        }

        if ($this->activeType === TodoType::IN_PROGRESS) {
            $this->swapCursor();

            return;
        }
    }

    /**
     * Swap the cursor between todo and in progress.
     */
    public function swapCursor(int $index = 0, int $page = 1, bool $focusLast = false): void
    {
        $activeType = $this->activeType;
        $swapTo = $activeType === TodoType::TODO ? TodoType::IN_PROGRESS : TodoType::TODO;
        $this->resetCursor($swapTo, $index, $page, $focusLast);
        $this->resetCursor($activeType);
        $this->activeType = $swapTo;

        // if the new active type has no items, simply reset both cursors and deactivate the active type.
        if ($this->manager->getByType($swapTo, $this->getCursor($swapTo))->total() === 0) {
            $this->activeType = null;
            $this->resetCursor(TodoType::IN_PROGRESS);
            $this->resetCursor(TodoType::TODO);
        }

    }

    /**
     * Move the cursor right.
     */
    protected function moveCursorRight(): void
    {
        $cursor = $this->getCursor();

        if (is_null($cursor)) {
            return;
        }

        if ($this->inProgress->count() === 0) {
            return;
        }

        if ($this->activeType === TodoType::TODO) {
            $this->swapCursor();

            return;
        }

    }

    /** Reset cursor by type. */
    protected function resetCursor(TodoType $type, int $index = Cursor::INACTIVE, int $page = Cursor::INITIAL_PAGE, bool $focusLast = false): void
    {
        if ($focusLast) {
            $paginator = $this->manager->getLastPageItems($type);
            $index = $paginator->count() - 1;
            $page = $paginator->lastPage();
        }
        $this->cursors[$type->value] = new Cursor($index, $page);
    }

    /**
     * Get the cursor for the active type.
     */
    protected function getCursor(?TodoType $type = null): ?Cursor
    {
        if (! is_null($type)) {
            return $this->cursors[$type->value];
        }

        if ($this->activeType === TodoType::TODO) {
            return $this->cursors[TodoType::TODO->value];
        }

        if ($this->activeType === TodoType::IN_PROGRESS) {
            return $this->cursors[TodoType::IN_PROGRESS->value];
        }

        return null;
    }

    /**
     *  Start and render the TUI application and listen for events.
     */
    protected function start(): int
    {
        $action = null;

        // only reset/initialize if not set. Some actions (e.g create new todo) create a new instance of the app.
        if (! isset($this->activeType)) {
            $this->activeType = null;
        }
        if (! isset($this->cursors[TodoType::TODO->value])) {
            $this->cursors[TodoType::TODO->value] = new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE);
        }

        if (! isset($this->cursors[TodoType::IN_PROGRESS->value])) {
            $this->cursors[TodoType::IN_PROGRESS->value] = new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE);
        }

        while (true) {
            while (null != ($event = static::$terminal->events()->next())) {

                if ($event instanceof CharKeyEvent && $event->modifiers === KeyModifiers::NONE) {
                    if ($event->char === 'q') {
                        break 2;
                    }

                    if ($event->char == 'n') {
                        $action = function () {
                            $this->manager->createInteractively();
                            $paginator = $this->manager->getLastPageItems(TodoType::TODO);
                            $index = $paginator->count() - 1;
                            $page = $paginator->lastPage();

                            return $this->restartApp(TodoType::TODO, new Cursor($index, $page));
                        };
                        break 2;
                    }
                    if ($event->char == 'e' && ! is_null($this->activeType)) {
                        $action = function () {
                            $this->manager->editInteractively();

                            return $this->restartApp($this->activeType, $this->getCursor($this->activeType));
                        };
                        break 2;
                    }

                    if ($event->char === 'j') {
                        $this->moveCursorDown();
                    }

                    if ($event->char === 'k') {
                        $this->moveCursorUp();
                    }

                    if ($event->char === 'h') {
                        $this->moveCursorLeft();
                    }

                    if ($event->char === 'l') {
                        $this->moveCursorRight();
                    }

                    if ($event->char === '[' && ! is_null($this->activeType)) {
                        $this->manager->repositionActiveItem(-1);
                        $this->moveCursorUp();
                    }
                    // move current item to next position and reindex items
                    if ($event->char == ']' && ! is_null($this->activeType)) {
                        $this->manager->repositionActiveItem(1);
                        $this->moveCursorDown();
                    }

                    if ($event->char === 'x' && ! is_null($this->activeType)) {
                        $this->handleDeleteTodo();
                    }

                }

                if ($event instanceof CodedKeyEvent) {

                    if ($event->code == KeyCode::Down) {
                        $this->moveCursorDown();
                    }

                    if ($event->code == KeyCode::Up) {
                        $this->moveCursorUp();
                    }

                    if ($event->code == KeyCode::Left) {
                        $this->moveCursorLeft();
                    }

                    if ($event->code == KeyCode::Right) {
                        $this->moveCursorRight();
                    }

                    if ($event->code == KeyCode::Enter) {
                        $this->handleEnterKey();
                    }

                    if ($event->code == KeyCode::Backspace && $this->activeType === TodoType::IN_PROGRESS) {
                        $this->manager->move($this->manager->getActiveTodo(), TodoType::TODO);
                        $this->resetCursor(TodoType::IN_PROGRESS);
                        $this->activeType = TodoType::TODO;
                        $this->resetCursor(TodoType::TODO, focusLast: true);
                    }
                }
            }

            $this->todos = $this->manager->getByType(TodoType::TODO, $this->getCursor(TodoType::TODO));

            $this->inProgress = $this->manager->getByType(TodoType::IN_PROGRESS, $this->getCursor(TodoType::IN_PROGRESS));

            $this->display->draw($this->widget());
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
     * Handle deletion of active todo and adjust cursor position.
     */
    protected function handleDeleteTodo(): void
    {
        $activeTodo = $this->manager->getActiveTodo();
        $lastIndex = $this->manager->getActiveIndex();

        $this->manager->delete($activeTodo);
        $cursor = $this->getCursor();

        // Figure out the new index to focus on after deletion.
        while (true) {
            // Get the latest items for the active type.
            $items = $this->manager->getByType($this->activeType, $cursor);

            // If the last index is 0 and we are on the first page and there are no more items, simply reset cursor.
            if ($lastIndex === 0 && $cursor->page() === Cursor::INITIAL_PAGE && $items->count() === 0) {
                $this->resetCursor($this->activeType);
                break;
            }

            // If the last index is 0 and there are items on the current page, focus on the next item.
            if ($lastIndex === 0 && $items->count() > 0) {
                $this->resetCursor($this->activeType, index: 0, page: $cursor->page());
                break;
            }

            // If there are no more items, reset the cursor.
            if ($items->total() == 0) {
                $this->resetCursor($this->activeType);
                break;
            }

            // Choose last on previous page.
            if ($items->count() === 0) {
                $this->resetCursor($this->activeType, index: DataManager::PAGINATE_BY - 1, page: $cursor->page() - 1);
                break;
            }

            // If there are still items on the current page, focus on the item before the item we just deleted.
            if ($items->count() > 0) {
                $newIndex = $lastIndex - 1;
                $this->resetCursor($this->activeType, $newIndex, page: $cursor->page());
                break;
            }
        }
    }

    /**
     * Handle the Enter key press to progress or complete todos.
     */
    protected function handleEnterKey(): void
    {
        $activeTodo = $this->manager->getActiveTodo();
        $isBeingDone = $activeTodo->type == TodoType::IN_PROGRESS;

        if ($this->context->config('delete_done', true) && $isBeingDone) {
            $this->manager->delete($activeTodo);
        } else {
            $this->manager->move($activeTodo, $isBeingDone ? TodoType::DONE : TodoType::IN_PROGRESS);
        }

        if (! $isBeingDone) {
            $this->swapCursor(focusLast: true);
        } else {
            $this->resetCursor(TodoType::IN_PROGRESS, index: 0, page: Cursor::INITIAL_PAGE);

            if ($this->manager->getByType(TodoType::IN_PROGRESS, $this->getCursor(TodoType::IN_PROGRESS))->total() === 0) {
                $this->swapCursor();
            }
        }
    }

    /**
     * Restart the app with the given type and cursor.
     */
    protected function restartApp(?TodoType $type = null, ?Cursor $cursor = null): int
    {
        clear();
        $app = new self($this->context, $this->version);

        if ($type !== null) {
            $app->setActiveType($type);
        }

        if ($cursor !== null && $type !== null) {
            $app->setCursor($type, $cursor);
        }

        return $app->run();
    }

    /**
     * Get style object to use on in the widget.
     */
    protected function getStyle(): Style
    {
        return \Kantui\default_style();
    }

    /**
     * Return the widget to be rendered.
     */
    protected function widget(): Widget
    {
        return GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(Constraint::percentage(99), Constraint::percentage(1))
            ->widgets(
                BlockWidget::default()
                    ->borders(Borders::ALL)
                    ->titles(
                        Title::fromString(
                            "Kantui: v$this->version | Context: {$this->context}"
                        )
                    )
                    ->style($this->getStyle())
                    ->widget(
                        GridWidget::default()
                            ->direction(Direction::Horizontal)
                            ->constraints(
                                Constraint::percentage(50),
                                Constraint::percentage(50)
                            )
                            ->widgets(
                                $this->manager->makeWidget(TodoType::TODO, $this->todos, $this->getCursor(TodoType::TODO)),
                                $this->manager->makeWidget(TodoType::IN_PROGRESS, $this->inProgress, $this->getCursor(TodoType::IN_PROGRESS)),
                            )
                    ),
                ParagraphWidget::fromText(
                    Text::fromString(
                        '  j (↓) | k (↑) | h (←) | l (→) | ENTER (progress) | BACKSPACE (move back) | [ (move item up) | ] (move item down) | n (new) | e (edit) | x (delete) | q (quit)'
                    )
                )->alignment(HorizontalAlignment::Right)->style($this->getStyle())
            );
    }
}
