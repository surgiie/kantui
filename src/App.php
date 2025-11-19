<?php

namespace Kantui;

use Illuminate\Pagination\LengthAwarePaginator;
use Kantui\Support\Context;
use Kantui\Support\Cursor;
use Kantui\Support\DataManager;
use Kantui\Support\Enums\TodoType;
use Kantui\Support\Enums\TodoUrgency;
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
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class App
{
    /**
     * Layout percentage constants.
     */
    private const LAYOUT_MAIN_PERCENTAGE = 99;

    private const LAYOUT_FOOTER_PERCENTAGE = 1;

    private const LAYOUT_HALF_PERCENTAGE = 50;

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
     * The cursor instance.
     */
    protected Cursor $cursor;

    /**
     * The currently active todo type.
     */
    protected ?TodoType $activeType = null;

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

        $this->cursor = new Cursor(0, Cursor::INITIAL_PAGE);
        $this->activeType = TodoType::TODO;

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

    /**
     * Set the active type.
     */
    public function setActiveType(TodoType $type): void
    {
        $this->activeType = $type;
    }

    /**
     * Set the cursor position.
     */
    public function setCursor(TodoType $type, Cursor $cursor): void
    {
        $this->cursor->setIndex($cursor->index())->setPage($cursor->page());
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
    }

    /**
     * Get the active items paginator based on the current active type.
     */
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
        if (is_null($this->activeType)) {
            $this->activeType = TodoType::TODO;

            if ($this->todos->count() === 0 && $this->inProgress->count() > 0) {
                $this->activeType = TodoType::IN_PROGRESS;
            }
        }

        $activeItems = $this->getActiveItems();

        if ($activeItems === null || $activeItems->count() === 0) {
            return;
        }

        $itemsOnPage = $activeItems->count();

        // Prevent cursor from going out of bounds
        if ($this->cursor->index() >= $itemsOnPage) {
            return;
        }

        // If we can move down within the current page
        if ($this->cursor->index() < $itemsOnPage - 1) {
            $this->cursor->increment();

            return;
        }

        // If we're at the end of the page and there are more pages
        if ($this->cursor->index() == $itemsOnPage - 1 && $activeItems->hasMorePages()) {
            $this->cursor->setIndex(0)->setPage($this->cursor->nextPage());
        }
    }

    /**
     * Move the cursor up.
     */
    protected function moveCursorUp(): void
    {
        $activeItems = $this->getActiveItems();

        if ($activeItems === null || $activeItems->count() === 0) {
            return;
        }

        $itemsOnPage = $activeItems->count();

        // Clamp cursor to valid range if it's out of bounds
        if ($this->cursor->index() >= $itemsOnPage) {
            $this->cursor->setIndex($itemsOnPage - 1);

            return;
        }

        // If we can move up within the current page
        if ($this->cursor->index() > 0) {
            $this->cursor->decrement();

            return;
        }

        // If we're at the start of the page and not on the first page
        if ($this->cursor->index() === 0 && $this->cursor->page() > Cursor::INITIAL_PAGE) {
            $this->cursor->setIndex(DataManager::PAGINATE_BY - 1)->setPage($this->cursor->previousPage());
        }
    }

    /**
     * Move the cursor left.
     */
    protected function moveCursorLeft(): void
    {
        if ($this->todos->count() === 0) {
            return;
        }

        // Cycle left: IN_PROGRESS -> TODO
        if ($this->activeType === TodoType::IN_PROGRESS) {
            $this->activeType = TodoType::TODO;
            $this->cursor->setIndex(0)->setPage(Cursor::INITIAL_PAGE);
        }
    }

    /**
     * Swap the cursor between todo and in progress.
     */
    public function swapCursor(bool $focusLast = false): void
    {
        $swapTo = $this->activeType->opposite();
        $this->activeType = $swapTo;

        if ($focusLast) {
            $paginator = $this->manager->getLastPageItems($swapTo);
            $this->cursor->setIndex($paginator->count() - 1)->setPage($paginator->lastPage());
        } else {
            $this->cursor->setIndex(0)->setPage(Cursor::INITIAL_PAGE);
        }

        // if the new active type has no items, simply reset cursor and set active type to null
        if ($this->manager->getByType($swapTo, $this->cursor)->total() === 0) {
            $this->cursor->setIndex(0)->setPage(Cursor::INITIAL_PAGE);
            $this->activeType = TodoType::TODO;
        }
    }

    /**
     * Move the cursor right.
     */
    protected function moveCursorRight(): void
    {
        if ($this->inProgress->count() === 0) {
            return;
        }

        // Cycle right: TODO -> IN_PROGRESS
        if ($this->activeType === TodoType::TODO) {
            $this->activeType = TodoType::IN_PROGRESS;
            $this->cursor->setIndex(0)->setPage(Cursor::INITIAL_PAGE);
        }
    }

    /**
     * Reset the cursor position.
     */
    protected function resetCursor(TodoType $type, int $index = Cursor::INACTIVE, int $page = Cursor::INITIAL_PAGE, bool $focusLast = false): void
    {
        if ($focusLast) {
            $paginator = $this->manager->getLastPageItems($type);
            $index = $paginator->count() - 1;
            $page = $paginator->lastPage();
        }
        $this->cursor->setIndex($index)->setPage($page);
    }

    /**
     * Get the cursor.
     */
    protected function getCursor(): Cursor
    {
        return $this->cursor;
    }

    /**
     * Handle character key events.
     */
    protected function handleCharKeyEvent(CharKeyEvent $event): callable|false|null
    {
        if ($event->modifiers !== KeyModifiers::NONE) {
            return null;
        }

        if ($event->char === 'q') {
            return null; // Signal quit
        }

        if ($event->char == 'n') {
            return function () {
                $this->manager->createInteractively();
                $paginator = $this->manager->getLastPageItems(TodoType::TODO);
                $index = $paginator->count() - 1;
                $page = $paginator->lastPage();

                return $this->restartApp(TodoType::TODO, new Cursor($index, $page));
            };
        }

        if ($event->char == 'e' && ! is_null($this->activeType)) {
            return function () {
                $this->manager->editInteractively();

                return $this->restartApp($this->activeType, $this->cursor);
            };
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
            // Prevent repositioning when filters are active
            if (! $this->manager->getSearchFilter()->isActive()) {
                $this->manager->repositionActiveItem(-1);
                $this->moveCursorUp();
            }
        }

        if ($event->char == ']' && ! is_null($this->activeType)) {
            // Prevent repositioning when filters are active
            if (! $this->manager->getSearchFilter()->isActive()) {
                $this->manager->repositionActiveItem(1);
                $this->moveCursorDown();
            }
        }

        if ($event->char === 'x' && ! is_null($this->activeType)) {
            $this->handleDeleteTodo();
        }

        if ($event->char === '/') {
            return function () {
                $this->handleSearch();

                return $this->restartApp($this->activeType, new Cursor(0, Cursor::INITIAL_PAGE));
            };
        }

        if ($event->char === 'f') {
            return function () {
                $this->handleFilterByUrgency();

                return $this->restartApp($this->activeType, new Cursor(0, Cursor::INITIAL_PAGE));
            };
        }

        if ($event->char === 'c') {
            $this->manager->getSearchFilter()->clear();
            $this->resetCursor(TodoType::TODO, 0, Cursor::INITIAL_PAGE);
        }

        return false; // Continue event loop
    }

    /**
     * Handle coded key events.
     */
    protected function handleCodedKeyEvent(CodedKeyEvent $event): void
    {
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
            $this->activeType = TodoType::TODO;
            $this->resetCursor(TodoType::TODO, focusLast: true);
        }
    }

    /**
     *  Start and render the TUI application and listen for events.
     */
    protected function start(): int
    {
        $action = null;

        while (true) {
            while (null != ($event = static::$terminal->events()->next())) {

                if ($event instanceof CharKeyEvent) {
                    $result = $this->handleCharKeyEvent($event);

                    if ($result === null) {
                        break 2; // Quit
                    }

                    if (is_callable($result)) {
                        $action = $result;
                        break 2;
                    }
                }

                if ($event instanceof CodedKeyEvent) {
                    $this->handleCodedKeyEvent($event);
                }
            }

            $this->todos = $this->manager->getByType(
                TodoType::TODO,
                $this->activeType === TodoType::TODO ? $this->cursor : new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE)
            );

            $this->inProgress = $this->manager->getByType(
                TodoType::IN_PROGRESS,
                $this->activeType === TodoType::IN_PROGRESS ? $this->cursor : new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE)
            );

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
     * Adjust cursor position after deleting a todo.
     */
    protected function adjustCursorAfterDeletion(int $lastIndex, Cursor $cursor, LengthAwarePaginator $items): void
    {
        // If the last index is 0 and we are on the first page and there are no more items, simply reset cursor.
        if ($lastIndex === 0 && $cursor->page() === Cursor::INITIAL_PAGE && $items->count() === 0) {
            $this->resetCursor($this->activeType);

            return;
        }

        // If the last index is 0 and there are items on the current page, focus on the next item.
        if ($lastIndex === 0 && $items->count() > 0) {
            $this->resetCursor($this->activeType, index: 0, page: $cursor->page());

            return;
        }

        // If there are no more items, reset the cursor.
        if ($items->total() == 0) {
            $this->resetCursor($this->activeType);

            return;
        }

        // Choose last on previous page.
        if ($items->count() === 0) {
            $this->resetCursor($this->activeType, index: DataManager::PAGINATE_BY - 1, page: $cursor->page() - 1);

            return;
        }

        // If there are still items on the current page, focus on the item before the item we just deleted.
        if ($items->count() > 0) {
            $newIndex = $lastIndex - 1;
            $this->resetCursor($this->activeType, $newIndex, page: $cursor->page());
        }
    }

    /**
     * Handle deletion of active todo and adjust cursor position.
     */
    protected function handleDeleteTodo(): void
    {
        $activeTodo = $this->manager->getActiveTodo();
        $lastIndex = $this->manager->getActiveIndex();

        $this->manager->delete($activeTodo);

        // Get the latest items for the active type after deletion.
        $items = $this->manager->getByType($this->activeType, $this->cursor);

        $this->adjustCursorAfterDeletion($lastIndex, $this->cursor, $items);
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

            if ($this->manager->getByType(TodoType::IN_PROGRESS, $this->cursor)->total() === 0) {
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
     * Handle search input from the user.
     */
    protected function handleSearch(): void
    {
        $currentQuery = $this->manager->getSearchFilter()->getSearchQuery();

        $query = text(
            label: 'Search todos (title/description):',
            default: $currentQuery ?? '',
            hint: 'Leave empty to clear search'
        );

        $this->manager->getSearchFilter()->setSearchQuery($query);
    }

    /**
     * Handle urgency filter selection from the user.
     */
    protected function handleFilterByUrgency(): void
    {
        $currentFilter = $this->manager->getSearchFilter()->getUrgencyFilter();

        $urgency = select(
            label: 'Filter by urgency:',
            options: [
                'all' => 'All (Clear filter)',
                TodoUrgency::LOW->value => TodoUrgency::LOW->label(),
                TodoUrgency::NORMAL->value => TodoUrgency::NORMAL->label(),
                TodoUrgency::IMPORTANT->value => TodoUrgency::IMPORTANT->label(),
                TodoUrgency::URGENT->value => TodoUrgency::URGENT->label(),
            ],
            default: $currentFilter->value ?? 'all'
        );

        if ($urgency === 'all') {
            $this->manager->getSearchFilter()->setUrgencyFilter(null);
        } else {
            $this->manager->getSearchFilter()->setUrgencyFilter(TodoUrgency::from($urgency));
        }
    }

    /**
     * Get style object to use on in the widget.
     */
    protected function getStyle(): Style
    {
        return \Kantui\default_style();
    }

    /**
     * Get the help text for the footer based on current state.
     */
    protected function getHelpText(): string
    {
        $searchFilter = $this->manager->getSearchFilter();

        if ($searchFilter->isActive()) {
            // When filtering, exclude item repositioning commands
            return '  j (↓) | k (↑) | h (←) | l (→) | ENTER (progress) | BACKSPACE (move back) | n (new) | e (edit) | x (delete) | / (search) | f (filter) | c (clear filters) | q (quit)';
        }

        // Normal mode with all commands
        return '  j (↓) | k (↑) | h (←) | l (→) | ENTER (progress) | BACKSPACE (move back) | [ (move item up) | ] (move item down) | n (new) | e (edit) | x (delete) | / (search) | f (filter) | c (clear filters) | q (quit)';
    }

    /**
     * Return the widget to be rendered.
     */
    protected function widget(): Widget
    {
        $searchFilter = $this->manager->getSearchFilter();
        $filterDescription = $searchFilter->getDescription();

        $title = "Kantui: v$this->version | Context: {$this->context}";

        if ($filterDescription !== '') {
            $title .= " | Filtered: $filterDescription";
        }

        return GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(Constraint::percentage(self::LAYOUT_MAIN_PERCENTAGE), Constraint::percentage(self::LAYOUT_FOOTER_PERCENTAGE))
            ->widgets(
                BlockWidget::default()
                    ->borders(Borders::ALL)
                    ->titles(
                        Title::fromString($title)
                    )
                    ->style($this->getStyle())
                    ->widget(
                        GridWidget::default()
                            ->direction(Direction::Horizontal)
                            ->constraints(
                                Constraint::percentage(self::LAYOUT_HALF_PERCENTAGE),
                                Constraint::percentage(self::LAYOUT_HALF_PERCENTAGE)
                            )
                            ->widgets(
                                $this->manager->makeWidget(
                                    TodoType::TODO,
                                    $this->todos,
                                    $this->activeType === TodoType::TODO ? $this->cursor : new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE)
                                ),
                                $this->manager->makeWidget(
                                    TodoType::IN_PROGRESS,
                                    $this->inProgress,
                                    $this->activeType === TodoType::IN_PROGRESS ? $this->cursor : new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE)
                                ),
                            )
                    ),
                ParagraphWidget::fromText(
                    Text::fromString($this->getHelpText())
                )->alignment(HorizontalAlignment::Right)->style($this->getStyle())
            );
    }
}
