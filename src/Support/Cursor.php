<?php

namespace Kantui\Support;

class Cursor
{
    public function __construct(protected int $index, protected int $page) {}

    /** Get the current index. */
    public function index(): int
    {
        return $this->index;
    }

    /** Set the page. */
    public function setPage(int $page): static
    {
        $this->page = $page;

        return $this;
    }

    /** Get the current page. */
    public function page(): int
    {
        return $this->page;
    }

    /** Set index. */
    public function setIndex(int $index): static
    {
        $this->index = $index;

        return $this;
    }

    /** Increment page count. */
    public function nextPage(): int
    {
        return $this->page + 1;
    }

    /** Decrement page count. */
    public function previousPage(): int
    {
        return $this->page - 1;
    }

    /** Decrement the index. */
    public function decrement(): static
    {
        $this->index--;

        return $this;
    }

    /** Increment the index. */
    public function increment(): static
    {
        $this->index++;

        return $this;
    }
}
