<?php

namespace Kantui\Widgets;

use Illuminate\Pagination\LengthAwarePaginator;
use Kantui\App;
use Kantui\Contracts\AppWidget;
use Kantui\Support\Context;
use Kantui\Support\Cursor;
use Kantui\Support\DataManager;
use Kantui\Support\Enums\TodoType;
use Kantui\Support\Enums\TodoUrgency;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

class MainWidget implements AppWidget
{
    /**
     * Layout percentage constants.
     */
    private const LAYOUT_HALF_PERCENTAGE = 50;

    /**
     * The cursor instance.
     */
    protected Cursor $cursor;

    /**
     * The currently active todo type.
     */
    protected ?TodoType $activeType = null;

    /**
     * The current page of todos.
     */
    protected LengthAwarePaginator $todos;

    /**
     * The current page of in progress todos.
     */
    protected LengthAwarePaginator $inProgress;

    public function __construct(
        protected DataManager $manager,
        protected Context $context,
        protected string $version,
        Cursor $cursor,
        TodoType $activeType
    ) {
        $this->cursor = $cursor;
        $this->activeType = $activeType;
        $this->refreshData();
    }

    /**
     * Refresh the todo data from the manager.
     */
    protected function refreshData(): void
    {
        // Use appropriate cursor for each type
        $todoCursor = $this->activeType === TodoType::TODO
            ? $this->cursor
            : new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE);

        $inProgressCursor = $this->activeType === TodoType::IN_PROGRESS
            ? $this->cursor
            : new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE);

        $this->todos = $this->manager->getByType(TodoType::TODO, $todoCursor);
        $this->inProgress = $this->manager->getByType(TodoType::IN_PROGRESS, $inProgressCursor);
    }

    /**
     * {@inheritdoc}
     */
    public function render(): Widget
    {
        $searchFilter = $this->manager->getSearchFilter();
        $filterDescription = $searchFilter->getDescription();

        $title = "Kantui: v$this->version | Context: {$this->context}";

        if ($filterDescription !== '') {
            $title .= " | Filtered: $filterDescription";
        }

        return BlockWidget::default()
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
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getFooterText(): string
    {
        return '  ? (help) | q (quit)';
    }

    /**
     * Get style object to use in the widget.
     */
    protected function getStyle(): Style
    {
        return \Kantui\default_style();
    }

    /**
     * {@inheritdoc}
     */
    public function handleCharKey(CharKeyEvent $event): callable|false|null
    {
        if ($event->modifiers !== KeyModifiers::NONE) {
            return false;
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

        if ($event->char == 'e' && ! is_null($this->manager->getActiveTodo())) {
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
            $searchFilter = $this->manager->getSearchFilter();

            if (! $searchFilter->isActive()) {
                return false; // No active filters to clear
            }

            return function () use ($searchFilter) {
                $searchFilter->clear();

                return $this->restartApp($this->activeType ?? TodoType::TODO, new Cursor(0, Cursor::INITIAL_PAGE));
            };
        }

        if ($event->char === '?') {
            // Toggle  help widget
            $app = App::getInstance();
            $searchFilter = $this->manager->getSearchFilter();
            $showReorderBindings = ! $searchFilter->isActive();

            $app->setActiveWidget(new HelpWidget($showReorderBindings, $this->getStyle(), $app));
        }

        if ($event->char === 'i' && ! is_null($this->activeType)) {
            // Show todo detail view
            $app = App::getInstance();
            $todo = $this->manager->getActiveTodo();

            if ($todo !== null) {
                $app->setActiveWidget(new TodoDetailWidget($todo, $this->getStyle(), $app));
            }
        }

        return false; // Continue event loop
    }

    /**
     * {@inheritdoc}
     */
    public function handleCodedKey(CodedKeyEvent $event): callable|false|null
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

            // Refresh both columns after move
            $this->refreshData();
        }

        return false; // Continue event loop
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

            $this->cursor->setIndex(0)->setPage(Cursor::INITIAL_PAGE);

            return;
        }

        $items = $this->getActiveItems();

        if ($items === null || $items->count() === 0) {
            return;
        }

        if ($this->cursor->index() < $items->count() - 1) {
            $this->cursor->increment();
        } elseif ($items->hasMorePages()) {
            $nextPage = $this->cursor->nextPage();
            $this->cursor->setPage($nextPage)->setIndex(0);
            $paginator = $this->manager->getByType($this->activeType, $this->cursor);
            $this->todos = $this->activeType === TodoType::TODO ? $paginator : $this->todos;
            $this->inProgress = $this->activeType === TodoType::IN_PROGRESS ? $paginator : $this->inProgress;
        }
    }

    /**
     * Move the cursor up.
     */
    protected function moveCursorUp(): void
    {
        if (is_null($this->activeType)) {
            return;
        }

        $items = $this->getActiveItems();

        if ($items === null || $items->count() === 0) {
            return;
        }

        if ($this->cursor->index() > 0) {
            $this->cursor->decrement();
        } elseif ($this->cursor->page() > Cursor::INITIAL_PAGE) {
            $prevPage = $this->cursor->previousPage();
            $this->cursor->setPage($prevPage);
            $paginator = $this->manager->getByType($this->activeType, $this->cursor);
            $this->todos = $this->activeType === TodoType::TODO ? $paginator : $this->todos;
            $this->inProgress = $this->activeType === TodoType::IN_PROGRESS ? $paginator : $this->inProgress;
            $this->cursor->setIndex($paginator->count() - 1);
        }
    }

    /**
     * Move the cursor left.
     */
    protected function moveCursorLeft(): void
    {
        if ($this->activeType === TodoType::IN_PROGRESS) {
            $this->swapCursor(TodoType::TODO);
        } elseif ($this->activeType === TodoType::TODO) {
            $this->swapCursor(TodoType::IN_PROGRESS);
        }
    }

    /**
     * Move the cursor right.
     */
    protected function moveCursorRight(): void
    {
        if ($this->activeType === TodoType::TODO) {
            $this->swapCursor(TodoType::IN_PROGRESS);
        } elseif ($this->activeType === TodoType::IN_PROGRESS) {
            $this->swapCursor(TodoType::TODO);
        }
    }

    /**
     * Swap the cursor to a different todo type.
     */
    protected function swapCursor(TodoType $newType): void
    {
        $this->activeType = $newType;
        $this->cursor->setIndex(0)->setPage(Cursor::INITIAL_PAGE);

        $newPaginator = $this->manager->getByType($newType, $this->cursor);

        if ($newPaginator->count() === 0) {
            return;
        }

        if ($newType === TodoType::TODO) {
            $this->todos = $newPaginator;
        } else {
            $this->inProgress = $newPaginator;
        }
    }

    /**
     * Reset the cursor position.
     */
    protected function resetCursor(TodoType $type, int $index = 0, int $page = Cursor::INITIAL_PAGE, bool $focusLast = false): void
    {
        $this->activeType = $type;
        $this->cursor->setIndex($index)->setPage($page);

        if ($focusLast) {
            $lastPageItems = $this->manager->getLastPageItems($type);
            $this->cursor->setIndex($lastPageItems->count() - 1)->setPage($lastPageItems->lastPage());
        }
    }

    /**
     * Get the cursor instance.
     */
    public function getCursor(): Cursor
    {
        return $this->cursor;
    }

    /**
     * Get the active type.
     */
    public function getActiveType(): ?TodoType
    {
        return $this->activeType;
    }

    /**
     * Handle the delete todo action.
     */
    protected function handleDeleteTodo(): void
    {
        $items = $this->getActiveItems();

        if ($items === null || $items->count() === 0) {
            return;
        }

        $todo = $this->manager->getActiveTodo();
        $this->manager->delete($todo);

        $this->adjustCursorAfterDeletion();
    }

    /**
     * Handle the enter key to progress todos.
     */
    protected function handleEnterKey(): void
    {
        if (is_null($this->activeType)) {
            return;
        }

        $items = $this->getActiveItems();

        if ($items === null || $items->count() === 0) {
            return;
        }

        $todo = $this->manager->getActiveTodo();
        $targetType = $this->activeType->opposite();

        $this->manager->move($todo, $targetType);

        // Switch to the target type and focus the last item (newly moved item)
        $this->activeType = $targetType;
        $this->resetCursor($targetType, focusLast: true);

        // Refresh both columns after move
        $this->refreshData();
    }

    /**
     * Adjust cursor position after a todo is deleted or moved.
     */
    protected function adjustCursorAfterDeletion(): void
    {
        $paginator = $this->manager->getByType($this->activeType, $this->cursor);
        $itemCount = $paginator->count();

        if ($this->activeType === TodoType::TODO) {
            $this->todos = $paginator;
        } else {
            $this->inProgress = $paginator;
        }

        // If current page is now empty
        if ($itemCount === 0) {
            // If we're not on the first page, go back a page
            if ($this->cursor->page() > Cursor::INITIAL_PAGE) {
                $previousPage = $this->cursor->previousPage();
                $this->cursor->setPage($previousPage);
                $paginator = $this->manager->getByType($this->activeType, $this->cursor);

                if ($this->activeType === TodoType::TODO) {
                    $this->todos = $paginator;
                } else {
                    $this->inProgress = $paginator;
                }

                $this->cursor->setIndex($paginator->count() - 1);
            } else {
                // We're on page 1 and it's empty - set cursor to inactive
                $this->cursor->setIndex(Cursor::INACTIVE);
            }

            return;
        }

        // If cursor index is beyond the new item count, adjust it
        if ($this->cursor->index() >= $itemCount) {
            $this->cursor->setIndex($itemCount - 1);
        }
    }

    /**
     * Handle search input.
     */
    protected function handleSearch(): void
    {
        clear();

        $query = text(
            label: 'Search Query',
            placeholder: 'Enter search query...',
            default: $this->manager->getSearchFilter()->getSearchQuery() ?? ''
        );

        $this->manager->getSearchFilter()->setSearchQuery($query);
    }

    /**
     * Handle filter by urgency.
     */
    protected function handleFilterByUrgency(): void
    {
        clear();

        $options = [
            'all' => 'All Urgencies',
            ...array_combine(
                array_map(fn ($case) => $case->value, TodoUrgency::cases()),
                array_map(fn ($case) => $case->label(), TodoUrgency::cases())
            ),
        ];

        $selected = select(
            label: 'Filter by Urgency',
            options: $options,
            default: $this->manager->getSearchFilter()->getUrgencyFilter()->value ?? 'all'
        );

        if ($selected === 'all') {
            $this->manager->getSearchFilter()->setUrgencyFilter(null);
        } else {
            $this->manager->getSearchFilter()->setUrgencyFilter(TodoUrgency::from($selected));
        }
    }

    /**
     * Restart the app with new cursor position.
     */
    protected function restartApp(TodoType $activeType, Cursor $cursor): int
    {
        return App::getInstance()->restartApp($activeType, $cursor);
    }
}
