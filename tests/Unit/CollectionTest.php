<?php

use Kantui\Support\Collection;

test('paginate returns length aware paginator', function () {
    $collection = Collection::make([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $paginated = $collection->paginate(perPage: 3, page: 1);

    expect($paginated->items())->toHaveCount(3)
        ->and($paginated->total())->toBe(10)
        ->and($paginated->currentPage())->toBe(1)
        ->and($paginated->lastPage())->toBe(4);
});

test('paginate second page', function () {
    $collection = Collection::make(['a', 'b', 'c', 'd', 'e', 'f']);
    $paginated = $collection->paginate(perPage: 2, page: 2);

    expect($paginated->items())->toBe(['c', 'd'])
        ->and($paginated->currentPage())->toBe(2);
});

test('paginate last page', function () {
    $collection = Collection::make([1, 2, 3, 4, 5]);
    $paginated = $collection->paginate(perPage: 2, page: 3);

    expect($paginated->items())->toHaveCount(1)
        ->and($paginated->items())->toBe([5]);
});

test('paginate empty collection', function () {
    $collection = Collection::make([]);
    $paginated = $collection->paginate(perPage: 5, page: 1);

    expect($paginated->items())->toHaveCount(0)
        ->and($paginated->total())->toBe(0);
});

test('paginate has more pages', function () {
    $collection = Collection::make(range(1, 10));
    $page1 = $collection->paginate(perPage: 3, page: 1);
    $page4 = $collection->paginate(perPage: 3, page: 4);

    expect($page1->hasMorePages())->toBeTrue()
        ->and($page4->hasMorePages())->toBeFalse();
});
