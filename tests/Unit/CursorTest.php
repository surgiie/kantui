<?php

use Kantui\Support\Cursor;

test('cursor initialization', function () {
    $cursor = new Cursor(5, 2);

    expect($cursor->index())->toBe(5)
        ->and($cursor->page())->toBe(2);
});

test('cursor can be inactive', function () {
    $cursor = new Cursor(Cursor::INACTIVE, Cursor::INITIAL_PAGE);

    expect($cursor->index())->toBe(Cursor::INACTIVE)
        ->and($cursor->page())->toBe(Cursor::INITIAL_PAGE);
});

test('set index', function () {
    $cursor = new Cursor(0, 1);
    $result = $cursor->setIndex(10);

    expect($cursor->index())->toBe(10)
        ->and($result)->toBe($cursor); // Test method chaining
});

test('set page', function () {
    $cursor = new Cursor(0, 1);
    $result = $cursor->setPage(5);

    expect($cursor->page())->toBe(5)
        ->and($result)->toBe($cursor); // Test method chaining
});

test('increment', function () {
    $cursor = new Cursor(5, 1);
    $cursor->increment();

    expect($cursor->index())->toBe(6);
});

test('decrement', function () {
    $cursor = new Cursor(5, 1);
    $cursor->decrement();

    expect($cursor->index())->toBe(4);
});

test('next page', function () {
    $cursor = new Cursor(0, 3);

    expect($cursor->nextPage())->toBe(4)
        // Original page should remain unchanged
        ->and($cursor->page())->toBe(3);
});

test('previous page', function () {
    $cursor = new Cursor(0, 3);

    expect($cursor->previousPage())->toBe(2)
        // Original page should remain unchanged
        ->and($cursor->page())->toBe(3);
});

test('method chaining', function () {
    $cursor = new Cursor(0, 1);

    $result = $cursor->setIndex(5)->setPage(3)->increment()->decrement();

    expect($result)->toBe($cursor)
        ->and($cursor->index())->toBe(5)
        ->and($cursor->page())->toBe(3);
});
