<?php

namespace Kantui\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Extended collection class with pagination support.
 *
 * This class extends Laravel's Collection to add pagination functionality
 * for displaying todo items across multiple pages in the TUI.
 */
class Collection extends BaseCollection
{
    /**
     * Paginate the collection.
     *
     * Creates a LengthAwarePaginator instance from the collection,
     * splitting items across multiple pages for display.
     *
     * @param  int  $perPage  Number of items to display per page
     * @param  int|null  $page  The current page number (resolves automatically if null)
     * @param  string  $pageName  The query parameter name for the page
     * @return LengthAwarePaginator The paginated collection
     */
    public function paginate(int $perPage, ?int $page = null, string $pageName = 'page'): LengthAwarePaginator
    {
        $page ??= LengthAwarePaginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            $this->forPage($page, $perPage)->values(),
            $this->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }
}
