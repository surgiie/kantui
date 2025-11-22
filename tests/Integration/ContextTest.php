<?php

use Kantui\Support\Context;

beforeEach(function () {
    $this->testDir = setupTestEnvironment();
});

afterEach(function () {
    cleanupTestEnvironment($this->testDir);
});

test('context creation', function () {
    $context = new Context('test-context');

    expect((string) $context)->toBe('test-context');
});

test('validates context name', function () {
    new Context('../../etc/passwd');
})->throws(RuntimeException::class, 'alphanumeric');

test('rejects empty context name', function () {
    new Context('');
})->throws(RuntimeException::class, 'cannot be empty');

test('rejects path traversal', function () {
    new Context('foo..bar');
})->throws(RuntimeException::class, 'alphanumeric');

test('path returns correct path', function () {
    $context = new Context('test');
    $path = $context->path();

    expect($path)->toContain('contexts/test');
});

test('path with relative path', function () {
    $context = new Context('test');
    $path = $context->path('data.json');

    expect($path)->toContain('contexts/test/data.json');
});

test('ensure defaults creates directory', function () {
    $context = new Context('test');
    $context->ensureDefaultFiles();

    expect($context->path())->toBeDirectory();
});

test('ensure defaults creates data file', function () {
    $context = new Context('test');
    $context->ensureDefaultFiles();

    $dataFile = $context->path('data.json');
    expect($dataFile)->toBeFile();

    $data = json_decode(file_get_contents($dataFile), true);
    expect($data)->toBeArray()
        ->toHaveKey('todo')
        ->toHaveKey('in_progress')
        ->toHaveKey('done');
});

test('config returns default when no config file', function () {
    $context = new Context('test');
    $value = $context->config('some-key', 'default-value');

    expect($value)->toBe('default-value');
});

test('config loads from file', function () {
    $context = new Context('test');
    $context->ensureDefaultFiles();

    $configPath = $context->path('config.json');
    file_put_contents($configPath, json_encode(['timezone' => 'America/New_York']));

    $context->loadConfig();

    expect($context->config('timezone'))->toBe('America/New_York');
});

test('get timezone returns default', function () {
    $context = new Context('test');

    $timezone = $context->getTimezone();

    expect($timezone)->toBeString()
        ->not->toBeEmpty();
});

test('directory permissions', function () {
    $context = new Context('test');
    $context->ensureDefaultFiles();

    $perms = fileperms($context->path());
    // Check that group write is not set (should be 0755 or similar)
    expect($perms & 0020)->toBe(0);
});
