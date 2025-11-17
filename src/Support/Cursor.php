<?php

namespace Kantui\Support;

/**
 * Manages cursor position and pagination for todo lists.
 *
 * The Cursor class tracks the current index within a paginated list of todos
 * and the current page number. It provides methods for navigation and state management.
 */
class Cursor
{
    public const INACTIVE = -1;

    public const INITIAL_PAGE = 1;

    /**
     * Create a new Cursor instance.
     *
     * @param  int  $index  The current index position (use INACTIVE for no selection)
     * @param  int  $page  The current page number (starts at INITIAL_PAGE)
     */
    public function __construct(protected int $index, protected int $page) {}

    /**
     * Get the current index.
     *
     * @return int The current cursor index, or INACTIVE if no item is selected
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Set the current page.
     *
     * @param  int  $page  The page number to set
     * @return static Returns this instance for method chaining
     */
    public function setPage(int $page): static
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Get the current page.
     *
     * @return int The current page number
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Set the current index.
     *
     * @param  int  $index  The index position to set
     * @return static Returns this instance for method chaining
     */
    public function setIndex(int $index): static
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Get the next page number.
     *
     * @return int The next page number (current page + 1)
     */
    public function nextPage(): int
    {
        return $this->page + 1;
    }

    /**
     * Get the previous page number.
     *
     * @return int The previous page number (current page - 1)
     */
    public function previousPage(): int
    {
        return $this->page - 1;
    }

    /**
     * Decrement the index by one.
     *
     * @return static Returns this instance for method chaining
     */
    public function decrement(): static
    {
        $this->index--;

        return $this;
    }

    /**
     * Increment the index by one.
     *
     * @return static Returns this instance for method chaining
     */
    public function increment(): static
    {
        $this->index++;

        return $this;
    }
}
