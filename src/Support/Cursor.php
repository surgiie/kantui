<?php

namespace Kantui\Support;

class Cursor
{
    public const INACTIVE = -1;

    public const INITIAL_PAGE = 1;

    public function __construct(protected int $index, protected int $page) {}

    /**
     * Get the current index.
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Set the current page.
     */
    public function setPage(int $page): static
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Get the current page.
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Set the current index.
     */
    public function setIndex(int $index): static
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Get the next page number.
     */
    public function nextPage(): int
    {
        return $this->page + 1;
    }

    /**
     * Get the previous page number.
     */
    public function previousPage(): int
    {
        return $this->page - 1;
    }

    /**
     * Decrement the index by one.
     */
    public function decrement(): static
    {
        $this->index--;

        return $this;
    }

    /**
     * Increment the index by one.
     */
    public function increment(): static
    {
        $this->index++;

        return $this;
    }
}
