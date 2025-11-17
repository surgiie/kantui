<?php

namespace Kantui\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Kantui\Support\Enums\TodoType;

/**
 * Manages cursor state and navigation across todo types.
 *
 * This class encapsulates all cursor-related logic including
 * navigation between todo items, pagination, and cursor position tracking.
 */
class CursorManager
{
    /**
     * The cursors for each todo type.
     *
     * @var array<string, Cursor>
     */
    private array $cursors = [];

    /**
     * The currently active todo type.
     */
    private ?TodoType $activeType = null;

    /**
     * Create a new CursorManager instance.
     */
    public function __construct()
    {
        $this->initializeCursors();
    }

    /**
     * Initialize cursors for all todo types.
     */
    private function initializeCursors(): void
    {
        $this->cursors = [
            TodoType::TODO->value => new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE),
            TodoType::IN_PROGRESS->value => new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE),
            TodoType::DONE->value => new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE),
        ];

        $this->activeType = TodoType::TODO;
        $this->cursors[TodoType::TODO->value]->setIndex(0);
    }

    /**
     * Get the cursor for a specific todo type.
     *
     * @param  TodoType  $type  The todo type
     * @return Cursor The cursor for that type
     */
    public function getCursor(TodoType $type): Cursor
    {
        return $this->cursors[$type->value];
    }

    /**
     * Get the currently active todo type.
     *
     * @return TodoType|null The active type or null
     */
    public function getActiveType(): ?TodoType
    {
        return $this->activeType;
    }

    /**
     * Set the active todo type.
     *
     * @param  TodoType  $type  The type to set as active
     */
    public function setActiveType(TodoType $type): void
    {
        $this->activeType = $type;
    }

    /**
     * Get the active cursor.
     *
     * @return Cursor|null The active cursor or null
     */
    public function getActiveCursor(): ?Cursor
    {
        if ($this->activeType === null) {
            return null;
        }

        return $this->cursors[$this->activeType->value];
    }

    /**
     * Move cursor down in the active list.
     *
     * @param  LengthAwarePaginator  $items  The paginated items for the active type
     */
    public function moveCursorDown(LengthAwarePaginator $items): void
    {
        $cursor = $this->getActiveCursor();
        if ($cursor === null) {
            return;
        }

        if ($cursor->index() < $items->count() - 1) {
            $cursor->increment();
        } elseif ($items->hasMorePages()) {
            $cursor->setPage($cursor->nextPage())->setIndex(0);
        }
    }

    /**
     * Move cursor up in the active list.
     *
     * @param  LengthAwarePaginator  $items  The paginated items for the active type
     */
    public function moveCursorUp(LengthAwarePaginator $items): void
    {
        $cursor = $this->getActiveCursor();
        if ($cursor === null) {
            return;
        }

        if ($cursor->index() > 0) {
            $cursor->decrement();
        } elseif ($cursor->page() > 1) {
            $cursor->setPage($cursor->previousPage())->setIndex(DataManager::PAGINATE_BY - 1);
        }
    }

    /**
     * Move cursor left (switch to previous todo type).
     */
    public function moveCursorLeft(): void
    {
        if ($this->activeType === null) {
            return;
        }

        $newType = match ($this->activeType) {
            TodoType::IN_PROGRESS => TodoType::TODO,
            TodoType::DONE => TodoType::IN_PROGRESS,
            default => $this->activeType,
        };

        $this->swapCursor($newType);
    }

    /**
     * Move cursor right (switch to next todo type).
     */
    public function moveCursorRight(): void
    {
        if ($this->activeType === null) {
            return;
        }

        $newType = match ($this->activeType) {
            TodoType::TODO => TodoType::IN_PROGRESS,
            TodoType::IN_PROGRESS => TodoType::DONE,
            default => $this->activeType,
        };

        $this->swapCursor($newType);
    }

    /**
     * Swap the active cursor to a different type.
     *
     * @param  TodoType  $newType  The new type to switch to
     * @param  bool  $focusLast  Whether to focus the last item in the new list
     */
    public function swapCursor(TodoType $newType, bool $focusLast = false): void
    {
        if ($this->activeType !== null) {
            $this->cursors[$this->activeType->value]->setIndex(Cursor::INACTIVE);
        }

        $this->activeType = $newType;
        $newCursor = $this->cursors[$newType->value];

        if ($focusLast) {
            $newCursor->setIndex(DataManager::PAGINATE_BY - 1);
        } else {
            $newCursor->setIndex(0);
        }
    }

    /**
     * Reset all cursors to their initial state.
     */
    public function reset(): void
    {
        $this->initializeCursors();
    }
}
