<?php

namespace Kantui\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    /**
     * Paginate the collection.
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
