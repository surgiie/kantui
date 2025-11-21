<?php

namespace Kantui\Support;

use Kantui\Support\Enums\TodoUrgency;

/**
 * Manages search and filter state for the kanban board.
 *
 * The SearchFilter class handles search queries and urgency filters,
 * providing methods to check if todos match the current criteria.
 */
class SearchFilter
{
    /**
     * The current search query.
     */
    protected ?string $searchQuery = null;

    /**
     * The currently active urgency filter.
     */
    protected ?TodoUrgency $urgencyFilter = null;

    /**
     * Set the search query.
     *
     * @param  string|null  $query  The search query, or null to clear
     */
    public function setSearchQuery(?string $query): void
    {
        $this->searchQuery = $query ? trim($query) : null;
    }

    /**
     * Get the current search query.
     *
     * @return string|null The current search query
     */
    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }

    /**
     * Set the urgency filter.
     *
     * @param  TodoUrgency|null  $urgency  The urgency level to filter by, or null to clear
     */
    public function setUrgencyFilter(?TodoUrgency $urgency): void
    {
        $this->urgencyFilter = $urgency;
    }

    /**
     * Get the current urgency filter.
     *
     * @return TodoUrgency|null The current urgency filter
     */
    public function getUrgencyFilter(): ?TodoUrgency
    {
        return $this->urgencyFilter;
    }

    /**
     * Check if a todo matches the current search query.
     *
     * Searches in tags and description (case-insensitive).
     *
     * @param  Todo  $todo  The todo to check
     * @return bool True if the todo matches the search query
     */
    public function matchesSearch(Todo $todo): bool
    {
        if ($this->searchQuery === null || $this->searchQuery === '') {
            return true;
        }

        $query = strtolower($this->searchQuery);
        $description = strtolower($todo->description);

        // Check if query matches any tag
        foreach ($todo->tags as $tag) {
            if (str_contains(strtolower($tag), $query)) {
                return true;
            }
        }

        // Check description
        return str_contains($description, $query);
    }

    /**
     * Check if a todo matches the current urgency filter.
     *
     * @param  Todo  $todo  The todo to check
     * @return bool True if the todo matches the urgency filter
     */
    public function matchesUrgency(Todo $todo): bool
    {
        if ($this->urgencyFilter === null) {
            return true;
        }

        return $todo->urgency === $this->urgencyFilter;
    }

    /**
     * Check if a todo matches all active filters.
     *
     * @param  Todo  $todo  The todo to check
     * @return bool True if the todo matches all active filters
     */
    public function matches(Todo $todo): bool
    {
        return $this->matchesSearch($todo) && $this->matchesUrgency($todo);
    }

    /**
     * Check if any filters are active.
     *
     * @return bool True if there are active filters
     */
    public function isActive(): bool
    {
        return $this->searchQuery !== null || $this->urgencyFilter !== null;
    }

    /**
     * Clear all filters and search query.
     */
    public function clear(): void
    {
        $this->searchQuery = null;
        $this->urgencyFilter = null;
    }

    /**
     * Get a human-readable description of active filters.
     *
     * @return string Description of active filters
     */
    public function getDescription(): string
    {
        $parts = [];

        if ($this->searchQuery !== null) {
            $parts[] = "Search: \"{$this->searchQuery}\"";
        }

        if ($this->urgencyFilter !== null) {
            $parts[] = "Urgency: {$this->urgencyFilter->label()}";
        }

        return empty($parts) ? '' : implode(' | ', $parts);
    }
}
