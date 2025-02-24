<?php

namespace Kantui\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    public function paginate($perPage, $page, $pageName = 'page'): LengthAwarePaginator
    {
        $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);

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
