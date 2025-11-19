<?php

use Kantui\Support\Context;
use Kantui\Support\Enums\TodoType;
use Kantui\Support\Enums\TodoUrgency;
use Kantui\Support\SearchFilter;
use Kantui\Support\Todo;

beforeEach(function () {
    $this->context = new Context('default');
    $this->filter = new SearchFilter;
});

it('can set and get search query', function () {
    $this->filter->setSearchQuery('test query');

    expect($this->filter->getSearchQuery())->toBe('test query');
});

it('trims search query', function () {
    $this->filter->setSearchQuery('  test query  ');

    expect($this->filter->getSearchQuery())->toBe('test query');
});

it('can clear search query with null', function () {
    $this->filter->setSearchQuery('test');
    $this->filter->setSearchQuery(null);

    expect($this->filter->getSearchQuery())->toBeNull();
});

it('can clear search query with empty string', function () {
    $this->filter->setSearchQuery('test');
    $this->filter->setSearchQuery('');

    expect($this->filter->getSearchQuery())->toBeNull();
});

it('can set and get urgency filter', function () {
    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    expect($this->filter->getUrgencyFilter())->toBe(TodoUrgency::URGENT);
});

it('can clear urgency filter', function () {
    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);
    $this->filter->setUrgencyFilter(null);

    expect($this->filter->getUrgencyFilter())->toBeNull();
});

it('matches todo with search query in title', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix bug in authentication',
        description: 'User login is broken',
        urgency: TodoUrgency::NORMAL,
        created_at: '2024-01-01 00:00:00'
    );

    $this->filter->setSearchQuery('authentication');

    expect($this->filter->matchesSearch($todo))->toBeTrue();
});

it('matches todo with search query in description', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix bug',
        description: 'User authentication is broken',
        urgency: TodoUrgency::NORMAL,
        created_at: '2024-01-01 00:00:00'
    );

    $this->filter->setSearchQuery('authentication');

    expect($this->filter->matchesSearch($todo))->toBeTrue();
});

it('search is case insensitive', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix Bug',
        description: 'Something',
        urgency: TodoUrgency::NORMAL,
        created_at: '2024-01-01 00:00:00'
    );

    $this->filter->setSearchQuery('bug');

    expect($this->filter->matchesSearch($todo))->toBeTrue();
});

it('does not match todo without search query', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix bug',
        description: 'User login is broken',
        urgency: TodoUrgency::NORMAL,
        created_at: '2024-01-01 00:00:00'
    );

    $this->filter->setSearchQuery('authentication');

    expect($this->filter->matchesSearch($todo))->toBeFalse();
});

it('matches any todo when no search query is set', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix bug',
        description: 'User login is broken',
        urgency: TodoUrgency::NORMAL,
        created_at: '2024-01-01 00:00:00'
    );

    expect($this->filter->matchesSearch($todo))->toBeTrue();
});

it('matches todo with correct urgency', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix bug',
        description: 'Something urgent',
        urgency: TodoUrgency::URGENT,
        created_at: '2024-01-01 00:00:00'
    );

    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    expect($this->filter->matchesUrgency($todo))->toBeTrue();
});

it('does not match todo with different urgency', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix bug',
        description: 'Something urgent',
        urgency: TodoUrgency::NORMAL,
        created_at: '2024-01-01 00:00:00'
    );

    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    expect($this->filter->matchesUrgency($todo))->toBeFalse();
});

it('matches any todo when no urgency filter is set', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix bug',
        description: 'Something',
        urgency: TodoUrgency::NORMAL,
        created_at: '2024-01-01 00:00:00'
    );

    expect($this->filter->matchesUrgency($todo))->toBeTrue();
});

it('matches todo with both search and urgency filters', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix authentication bug',
        description: 'Something',
        urgency: TodoUrgency::URGENT,
        created_at: '2024-01-01 00:00:00'
    );

    $this->filter->setSearchQuery('authentication');
    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    expect($this->filter->matches($todo))->toBeTrue();
});

it('does not match todo when search matches but urgency does not', function () {
    $todo = new Todo(
        $this->context,
        TodoType::TODO,
        id: '123',
        title: 'Fix authentication bug',
        description: 'Something',
        urgency: TodoUrgency::NORMAL,
        created_at: '2024-01-01 00:00:00'
    );

    $this->filter->setSearchQuery('authentication');
    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    expect($this->filter->matches($todo))->toBeFalse();
});

it('reports active when search query is set', function () {
    $this->filter->setSearchQuery('test');

    expect($this->filter->isActive())->toBeTrue();
});

it('reports active when urgency filter is set', function () {
    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    expect($this->filter->isActive())->toBeTrue();
});

it('reports inactive when no filters are set', function () {
    expect($this->filter->isActive())->toBeFalse();
});

it('can clear all filters', function () {
    $this->filter->setSearchQuery('test');
    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    $this->filter->clear();

    expect($this->filter->getSearchQuery())->toBeNull();
    expect($this->filter->getUrgencyFilter())->toBeNull();
    expect($this->filter->isActive())->toBeFalse();
});

it('generates description for search filter', function () {
    $this->filter->setSearchQuery('test query');

    expect($this->filter->getDescription())->toBe('Search: "test query"');
});

it('generates description for urgency filter', function () {
    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    expect($this->filter->getDescription())->toBe('Urgency: URGENT');
});

it('generates description for both filters', function () {
    $this->filter->setSearchQuery('test');
    $this->filter->setUrgencyFilter(TodoUrgency::URGENT);

    expect($this->filter->getDescription())->toBe('Search: "test" | Urgency: URGENT');
});

it('generates empty description when no filters are set', function () {
    expect($this->filter->getDescription())->toBe('');
});
