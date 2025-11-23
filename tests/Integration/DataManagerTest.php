<?php

use Kantui\Support\Context;
use Kantui\Support\Cursor;
use Kantui\Support\DataManager;
use Kantui\Support\Enums\TodoType;
use Kantui\Support\Todo;

beforeEach(function () {
    $this->testDir = setupTestEnvironment();
    $this->context = new Context('test');
    $this->context->ensureDefaultFiles();
});

afterEach(function () {
    cleanupTestEnvironment($this->testDir);
});

test('loads empty todos by default', function () {
    $manager = new DataManager($this->context);
    $todos = $manager->getByType(TodoType::TODO, new Cursor(Cursor::INACTIVE, 1));

    expect($todos)->toHaveCount(0);
});

test('default data structure', function () {
    $data = DataManager::defaultData();

    expect($data)->toHaveKey('todo')
        ->toHaveKey('in_progress')
        ->toHaveKey('done')
        ->and($data['todo'])->toBeArray()
        ->and($data['in_progress'])->toBeArray()
        ->and($data['done'])->toBeArray();
});

test('get by type returns paginated results', function () {
    // Create test data
    $dataFile = $this->context->path('data.json');
    $testData = [
        'todo' => [
            [
                'id' => '123',
                'tags' => ['test'],
                'description' => 'Test Description',
                'urgency' => 'normal',
                'created_at' => '2025-01-01 00:00:00',
            ],
        ],
        'in_progress' => [],
        'done' => [],
    ];
    file_put_contents($dataFile, json_encode($testData));

    $manager = new DataManager($this->context);
    $todos = $manager->getByType(TodoType::TODO, new Cursor(0, 1));

    expect($todos)->toHaveCount(1)
        ->and($todos->first())->toBeInstanceOf(Todo::class);
});

test('delete removes todo', function () {
    // Create test data
    $dataFile = $this->context->path('data.json');
    $testData = [
        'todo' => [
            [
                'id' => '123',
                'tags' => ['test'],
                'description' => 'Test Description',
                'urgency' => 'normal',
                'created_at' => '2025-01-01 00:00:00',
            ],
        ],
        'in_progress' => [],
        'done' => [],
    ];
    file_put_contents($dataFile, json_encode($testData));

    $manager = new DataManager($this->context);
    $todos = $manager->getByType(TodoType::TODO, new Cursor(0, 1));
    $todo = $todos->first();

    $manager->delete($todo);

    // Reload and verify
    $manager = new DataManager($this->context);
    $todos = $manager->getByType(TodoType::TODO, new Cursor(Cursor::INACTIVE, 1));
    expect($todos)->toHaveCount(0);
});

test('move changes todo type', function () {
    // Create test data
    $dataFile = $this->context->path('data.json');
    $testData = [
        'todo' => [
            [
                'id' => '123',
                'tags' => ['test', 'todo'],
                'description' => 'Test Description',
                'urgency' => 'normal',
                'created_at' => '2025-01-01 00:00:00',
            ],
        ],
        'in_progress' => [],
        'done' => [],
    ];
    file_put_contents($dataFile, json_encode($testData));

    $manager = new DataManager($this->context);
    $todos = $manager->getByType(TodoType::TODO, new Cursor(0, 1));
    $todo = $todos->first();

    $manager->move($todo, TodoType::IN_PROGRESS);

    // Reload and verify
    $manager = new DataManager($this->context);
    $todoItems = $manager->getByType(TodoType::TODO, new Cursor(Cursor::INACTIVE, 1));
    $inProgressItems = $manager->getByType(TodoType::IN_PROGRESS, new Cursor(0, 1));

    expect($todoItems)->toHaveCount(0)
        ->and($inProgressItems)->toHaveCount(1)
        ->and($inProgressItems->first()->tags)->toBe(['test', 'todo']);
});

test('get last page items', function () {
    // Create test data with multiple pages
    $testData = [
        'todo' => [],
        'in_progress' => [],
        'done' => [],
    ];

    // Add 13 items (will span 3 pages with PAGINATE_BY = 6)
    for ($i = 1; $i <= 13; $i++) {
        $testData['todo'][] = [
            'id' => "id-$i",
            'tags' => ["todo-$i"],
            'description' => "Description $i",
            'urgency' => 'normal',
            'created_at' => '2025-01-01 00:00:00',
        ];
    }

    $dataFile = $this->context->path('data.json');
    file_put_contents($dataFile, json_encode($testData));

    $manager = new DataManager($this->context);
    $lastPage = $manager->getLastPageItems(TodoType::TODO);

    expect($lastPage->currentPage())->toBe(3)
        ->and($lastPage)->toHaveCount(1); // Last page should have 1 item (13 % 6 = 1)
});
