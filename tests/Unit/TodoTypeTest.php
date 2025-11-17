<?php

use Kantui\Support\Enums\TodoType;

test('has correct values', function () {
    expect(TodoType::TODO->value)->toBe('todo')
        ->and(TodoType::IN_PROGRESS->value)->toBe('in_progress')
        ->and(TodoType::DONE->value)->toBe('done');
});

test('can create from string', function () {
    expect(TodoType::from('todo'))->toBe(TodoType::TODO)
        ->and(TodoType::from('in_progress'))->toBe(TodoType::IN_PROGRESS)
        ->and(TodoType::from('done'))->toBe(TodoType::DONE);
});

test('opposite returns correct types', function () {
    expect(TodoType::TODO->opposite())->toBe(TodoType::IN_PROGRESS)
        ->and(TodoType::IN_PROGRESS->opposite())->toBe(TodoType::TODO)
        ->and(TodoType::DONE->opposite())->toBe(TodoType::DONE);
});
