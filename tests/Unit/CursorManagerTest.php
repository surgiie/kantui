<?php

use Kantui\Support\Cursor;
use Kantui\Support\CursorManager;
use Kantui\Support\Enums\TodoType;

beforeEach(function () {
    $this->manager = new CursorManager;
});

test('initializes with todo active', function () {
    expect($this->manager->getActiveType())->toBe(TodoType::TODO);
});

test('get cursor returns cursor for type', function () {
    $cursor = $this->manager->getCursor(TodoType::TODO);

    expect($cursor)->toBeInstanceOf(Cursor::class);
});

test('get active cursor returns todo cursor initially', function () {
    $cursor = $this->manager->getActiveCursor();

    expect($cursor)->toBeInstanceOf(Cursor::class)
        ->and($cursor->index())->toBe(0);
});

test('set active type changes active type', function () {
    $this->manager->setActiveType(TodoType::IN_PROGRESS);

    expect($this->manager->getActiveType())->toBe(TodoType::IN_PROGRESS);
});

test('move cursor left switches from in progress to todo', function () {
    $this->manager->setActiveType(TodoType::IN_PROGRESS);
    $this->manager->moveCursorLeft();

    expect($this->manager->getActiveType())->toBe(TodoType::TODO);
});

test('move cursor right switches from todo to in progress', function () {
    $this->manager->moveCursorRight();

    expect($this->manager->getActiveType())->toBe(TodoType::IN_PROGRESS);
});

test('move cursor left from todo stays on todo', function () {
    $this->manager->moveCursorLeft();

    expect($this->manager->getActiveType())->toBe(TodoType::TODO);
});

test('move cursor right from done stays on done', function () {
    $this->manager->setActiveType(TodoType::DONE);
    $this->manager->moveCursorRight();

    expect($this->manager->getActiveType())->toBe(TodoType::DONE);
});

test('swap cursor deactivates old cursor', function () {
    $oldCursor = $this->manager->getActiveCursor();
    expect($oldCursor->index())->toBe(0);

    $this->manager->swapCursor(TodoType::IN_PROGRESS);

    expect($oldCursor->index())->toBe(Cursor::INACTIVE);
});

test('swap cursor activates new cursor', function () {
    $this->manager->swapCursor(TodoType::IN_PROGRESS);
    $newCursor = $this->manager->getActiveCursor();

    expect($newCursor->index())->toBe(0);
});

test('reset returns to initial state', function () {
    $this->manager->setActiveType(TodoType::DONE);
    $this->manager->reset();

    expect($this->manager->getActiveType())->toBe(TodoType::TODO)
        ->and($this->manager->getActiveCursor()->index())->toBe(0);
});
