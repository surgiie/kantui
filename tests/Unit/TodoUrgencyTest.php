<?php

use Kantui\Support\Enums\TodoUrgency;

test('has correct values', function () {
    expect(TodoUrgency::LOW->value)->toBe('low')
        ->and(TodoUrgency::NORMAL->value)->toBe('normal')
        ->and(TodoUrgency::IMPORTANT->value)->toBe('important')
        ->and(TodoUrgency::URGENT->value)->toBe('urgent');
});

test('can create from string', function () {
    expect(TodoUrgency::from('low'))->toBe(TodoUrgency::LOW)
        ->and(TodoUrgency::from('normal'))->toBe(TodoUrgency::NORMAL)
        ->and(TodoUrgency::from('important'))->toBe(TodoUrgency::IMPORTANT)
        ->and(TodoUrgency::from('urgent'))->toBe(TodoUrgency::URGENT);
});

test('label returns uppercase', function () {
    expect(TodoUrgency::LOW->label())->toBe('LOW')
        ->and(TodoUrgency::NORMAL->label())->toBe('NORMAL')
        ->and(TodoUrgency::IMPORTANT->label())->toBe('IMPORTANT')
        ->and(TodoUrgency::URGENT->label())->toBe('URGENT');
});
