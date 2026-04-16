<?php

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\ClassConst\RemoveUnusedPrivateClassConstantRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withRules([
        RemoveUnusedPrivateMethodRector::class,
        RemoveUnusedPrivateClassConstantRector::class,
        RemoveUnusedPrivatePropertyRector::class,
        RemoveUnusedVariableAssignRector::class,
        RemoveUnusedPrivateMethodParameterRector::class,
    ]);
