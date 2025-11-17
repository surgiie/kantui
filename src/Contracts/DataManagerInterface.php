<?php

namespace Kantui\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Kantui\Support\Cursor;
use Kantui\Support\Enums\TodoType;
use Kantui\Support\Todo;
use PhpTui\Tui\Widget\Widget;

/**
 * Contract for todo data management.
 *
 * Defines the interface for managing todo items including
 * CRUD operations, pagination, and widget rendering.
 */
interface DataManagerInterface
{
    /**
     * Load the todo items from the data file.
     *
     * @return array Array of Todo objects indexed by type
     */
    public function loadTodos(): array;

    /**
     * Get the last page of items for a given todo type.
     *
     * @param  TodoType  $type  The todo type to get the last page for
     * @return LengthAwarePaginator Paginated items from the last page
     */
    public function getLastPageItems(TodoType $type): LengthAwarePaginator;

    /**
     * Get the active todo item index.
     *
     * @return int The index of the currently active todo in its collection
     */
    public function getActiveIndex(): int;

    /**
     * Reposition active item by the given number of index counts.
     *
     * @param  int  $offset  The number of positions to move
     */
    public function repositionActiveItem(int $offset): void;

    /**
     * Edit the active todo item interactively using prompts.
     */
    public function editInteractively(): void;

    /**
     * Create a new todo item and save it to the data file.
     *
     * @return Todo The newly created todo item
     */
    public function createInteractively(): Todo;

    /**
     * Write the todos array to the data file as JSON.
     */
    public function writeTodos(): void;

    /**
     * Get todos by type and paginate them appropriately.
     *
     * @param  TodoType  $type  The type of todos to retrieve
     * @param  Cursor  $cursor  The cursor containing pagination information
     * @return LengthAwarePaginator Paginated collection of todos
     */
    public function getByType(TodoType $type, Cursor $cursor): LengthAwarePaginator;

    /**
     * Move a todo item to the given type.
     *
     * @param  Todo  $todo  The todo item to move
     * @param  TodoType  $type  The destination type
     */
    public function move(Todo $todo, TodoType $type): void;

    /**
     * Delete a todo item from the collection.
     *
     * @param  Todo  $todo  The todo item to delete
     */
    public function delete(Todo $todo): void;

    /**
     * Get the active todo item.
     *
     * @return Todo|null The currently active todo, or null if none selected
     */
    public function getActiveTodo(): ?Todo;

    /**
     * Get the widget for the given todo collection.
     *
     * @param  TodoType  $type  The type of todos in this widget
     * @param  LengthAwarePaginator  $todos  The paginated todos to display
     * @param  Cursor  $cursor  The current cursor state for highlighting
     * @return Widget The complete column widget for the TUI
     */
    public function makeWidget(TodoType $type, LengthAwarePaginator $todos, Cursor $cursor): Widget;
}
