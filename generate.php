<?php

/**
 * Utility script for generating test todo data.
 *
 * Usage:
 *   php generate.php <context> [options]
 *
 * Options:
 *   --force           Overwrite existing data
 *   --count=<n>       Number of todos per type (default: random 10-20)
 *   --todo=<n>        Number of 'todo' items (overrides --count)
 *   --progress=<n>    Number of 'in_progress' items (overrides --count)
 *
 * Examples:
 *   php generate.php mycontext
 *   php generate.php mycontext --count=15
 *   php generate.php mycontext --todo=10 --progress=5
 *   php generate.php mycontext --force --count=25
 */

use Kantui\Support\Context;

require __DIR__ . '/vendor/autoload.php';

/**
 * Parse command line arguments.
 */
function parseArguments(array $argv): array
{
    if (str_contains($argv[1] ?? '', '--')) {
        echo "Error: Context argument is missing or invalid.\n";
        showUsage();
    }
    $args = [
        'context' => $argv[1] ?? null,
        'force' => in_array('--force', $argv),
        'count' => null,
        'todo' => null,
        'progress' => null,
    ];

    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--count=')) {
            $args['count'] = (int) substr($arg, 8);
        } elseif (str_starts_with($arg, '--todo=')) {
            $args['todo'] = (int) substr($arg, 7);
        } elseif (str_starts_with($arg, '--progress=')) {
            $args['progress'] = (int) substr($arg, 11);
        }
    }

    return $args;
}

/**
 * Generate todos of a specific type.
 */
function generateTodos(Faker\Generator $faker, string $type, int $count): array
{
    $todos = [];

    for ($i = 0; $i < $count; $i++) {
        $todos[] = [
            'id' => $faker->uuid,
            'urgency' => $faker->randomElement(['low', 'normal', 'urgent']),
            'tags' => $faker->words(rand(1, 3)),
            'description' => $faker->paragraph,
            'type' => $type,
            'created_at' => $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
        ];
    }

    return $todos;
}

/**
 * Display usage information and exit.
 */
function showUsage(): never
{
    echo "Usage: php generate.php <context> [options]\n\n";
    echo "Description:\n";
    echo "  Generates test todo data for the specified context.\n\n";
    echo "Options:\n";
    echo "  --force           Overwrite existing data\n";
    echo "  --count=<n>       Number of todos per type (default: random 10-20)\n";
    echo "  --todo=<n>        Number of 'todo' items (overrides --count)\n";
    echo "  --progress=<n>    Number of 'in_progress' items (overrides --count)\n\n";
    echo "Examples:\n";
    echo "  php generate.php mycontext\n";
    echo "  php generate.php mycontext --count=15\n";
    echo "  php generate.php mycontext --todo=10 --progress=5\n";
    echo "  php generate.php mycontext --force --count=25\n";
    exit(1);
}

// Parse arguments
$args = parseArguments($argv);

if (! $args['context']) {
    showUsage();
}

// Initialize context
$context = new Context($args['context']);

// Check if data already exists
if (! $args['force'] && file_exists($path = $context->path('data.json'))) {
    echo "Data already exists at: $path\n";
    echo "Use --force to overwrite or remove the file manually.\n";
    exit(1);
}

$context->ensureDefaultFiles();

// Initialize Faker
$faker = Faker\Factory::create();

// Determine counts for each type
$todoCount = $args['todo'] ?? $args['count'] ?? rand(10, 20);
$progressCount = $args['progress'] ?? $args['count'] ?? rand(10, 20);

// Generate todos
$data = [
    'todo' => generateTodos($faker, 'todo', $todoCount),
    'in_progress' => generateTodos($faker, 'in_progress', $progressCount),
];

// Save to file
file_put_contents($context->path('data.json'), json_encode($data, JSON_PRETTY_PRINT));

// Display summary
echo "Generated test data for context: {$args['context']}\n";
echo "  - {$todoCount} todo items\n";
echo "  - {$progressCount} in_progress items\n";
echo "Saved to: {$context->path('data.json')}\n";
