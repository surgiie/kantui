<?php

use Kantui\Support\ConfigValidator;

test('validates valid config', function () {
    $config = [
        'timezone' => 'America/New_York',
        'human_readable_date' => true,
    ];

    $result = ConfigValidator::validate($config);

    expect($result)->toBe($config);
});

test('validates empty config', function () {
    $config = [];

    $result = ConfigValidator::validate($config);

    expect($result)->toBe($config);
});

test('rejects unknown key', function () {
    ConfigValidator::validate(['unknown_key' => 'value']);
})->throws(RuntimeException::class, 'Unknown configuration key');

test('rejects wrong type for timezone', function () {
    ConfigValidator::validate(['timezone' => 123]);
})->throws(RuntimeException::class, 'must be of type string');

test('rejects wrong type for human_readable_date', function () {
    ConfigValidator::validate(['human_readable_date' => 'yes']);
})->throws(RuntimeException::class, 'must be of type boolean');

test('rejects invalid timezone', function () {
    ConfigValidator::validate(['timezone' => 'Invalid/Timezone']);
})->throws(RuntimeException::class, 'Invalid timezone');

test('accepts valid timezone', function () {
    $config = ['timezone' => 'UTC'];

    $result = ConfigValidator::validate($config);

    expect($result)->toBe($config);
});

test('get valid keys', function () {
    $keys = ConfigValidator::getValidKeys();

    expect($keys)->toContain('timezone')
        ->and($keys)->toContain('human_readable_date');
});

test('get schema', function () {
    $schema = ConfigValidator::getSchema();

    expect($schema)->toHaveKey('timezone')
        ->and($schema)->toHaveKey('human_readable_date')
        ->and($schema['timezone'])->toBe('string')
        ->and($schema['human_readable_date'])->toBe('boolean');
});
