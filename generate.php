<?php

// utility script for generating data to test the application

use Kantui\Support\Context;

require __DIR__.'/vendor/autoload.php';

// generate data
$faker = Faker\Factory::create();
$todoTypes = ['todo', 'in_progress'];
$todos = [];

$context = $argv[1] ?? '';
if (! $context) {
    echo "Please provide a context. usage: php generate.php <context>\n";
    exit(1);
}

$context = new Context($context);
// prevent overwriting existing data
$isForce = in_array('--force', $argv);
if (! $isForce && file_exists($path = $context->path('data.json'))) {
    echo "Data already exists. Skipping generation. Remove $path to regenerate or use --force\n";
    exit(1);
}
$context->ensureDefaults();

foreach ($todoTypes as $type) {
    $todos[$type] = [];
    $total = rand(10, 20);
    for ($i = 0; $i < $total; $i++) {
        $todos[$type][] = [
            'id' => $faker->uuid,
            'urgency' => $faker->randomElement(['low', 'normal', 'urgent']),
            'title' => $faker->sentence,
            'description' => $faker->paragraph,
            'type' => $type,
            'created_at' => $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
        ];
    }
    echo "Generated $total $type todos\n";
}
$todos = json_encode($todos, JSON_PRETTY_PRINT);
file_put_contents($context->path('data.json'), $todos);
