<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function setupTestEnvironment(): string
{
    $testDir = sys_get_temp_dir().'/kantui-test-'.uniqid();
    putenv("KANTUI_HOME={$testDir}");

    return $testDir;
}

function cleanupTestEnvironment(string $testDir): void
{
    if (is_dir($testDir)) {
        recursiveDelete($testDir);
    }
    putenv('KANTUI_HOME');
}

function recursiveDelete(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? recursiveDelete($path) : unlink($path);
    }
    rmdir($dir);
}
