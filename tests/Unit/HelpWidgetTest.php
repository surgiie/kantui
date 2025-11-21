<?php

use Kantui\Widgets\HelpWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Style\Style;

test('widget renders with default settings', function () {
    $widget = new HelpWidget;
    $rendered = $widget->render();

    expect($rendered)->toBeInstanceOf(GridWidget::class);
});

test('widget renders with custom style', function () {
    $style = Style::default();
    $widget = new HelpWidget(true, $style);
    $rendered = $widget->render();

    expect($rendered)->toBeInstanceOf(GridWidget::class);
});

test('widget shows reorder bindings by default', function () {
    $widget = new HelpWidget;

    // Use reflection to access private buildHelpText method
    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('buildHelpText');
    $method->setAccessible(true);
    $helpText = $method->invoke($widget);

    expect($helpText)->toContain('ITEM REORDERING')
        ->and($helpText)->toContain('[')
        ->and($helpText)->toContain(']');
});

test('widget hides reorder bindings when disabled', function () {
    $widget = new HelpWidget(false);

    // Use reflection to access private buildHelpText method
    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('buildHelpText');
    $method->setAccessible(true);
    $helpText = $method->invoke($widget);

    expect($helpText)->not->toContain('ITEM REORDERING');
});

test('widget contains all navigation bindings', function () {
    $widget = new HelpWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('buildHelpText');
    $method->setAccessible(true);
    $helpText = $method->invoke($widget);

    expect($helpText)->toContain('NAVIGATION')
        ->and($helpText)->toContain('j or ↓')
        ->and($helpText)->toContain('k or ↑')
        ->and($helpText)->toContain('h or ←')
        ->and($helpText)->toContain('l or →');
});

test('widget contains all item action bindings', function () {
    $widget = new HelpWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('buildHelpText');
    $method->setAccessible(true);
    $helpText = $method->invoke($widget);

    expect($helpText)->toContain('ITEM ACTIONS')
        ->and($helpText)->toContain('ENTER')
        ->and($helpText)->toContain('BACKSPACE')
        ->and($helpText)->toContain('n')
        ->and($helpText)->toContain('e')
        ->and($helpText)->toContain('x');
});

test('widget contains all filter and search bindings', function () {
    $widget = new HelpWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('buildHelpText');
    $method->setAccessible(true);
    $helpText = $method->invoke($widget);

    expect($helpText)->toContain('FILTERING & SEARCH')
        ->and($helpText)->toContain('/')
        ->and($helpText)->toContain('f')
        ->and($helpText)->toContain('c');
});

test('widget contains all application bindings', function () {
    $widget = new HelpWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('buildHelpText');
    $method->setAccessible(true);
    $helpText = $method->invoke($widget);

    expect($helpText)->toContain('APPLICATION')
        ->and($helpText)->toContain('?')
        ->and($helpText)->toContain('q');
});

test('widget constants match expected values', function () {
    expect(HelpWidget::MOVE_DOWN)->toBe('j or ↓')
        ->and(HelpWidget::MOVE_UP)->toBe('k or ↑')
        ->and(HelpWidget::MOVE_LEFT)->toBe('h or ←')
        ->and(HelpWidget::MOVE_RIGHT)->toBe('l or →')
        ->and(HelpWidget::PROGRESS_ITEM)->toBe('ENTER')
        ->and(HelpWidget::REGRESS_ITEM)->toBe('BACKSPACE')
        ->and(HelpWidget::NEW_ITEM)->toBe('n')
        ->and(HelpWidget::EDIT_ITEM)->toBe('e')
        ->and(HelpWidget::DELETE_ITEM)->toBe('x')
        ->and(HelpWidget::MOVE_ITEM_UP)->toBe('[')
        ->and(HelpWidget::MOVE_ITEM_DOWN)->toBe(']')
        ->and(HelpWidget::SEARCH)->toBe('/')
        ->and(HelpWidget::FILTER_URGENCY)->toBe('f')
        ->and(HelpWidget::CLEAR_FILTERS)->toBe('c')
        ->and(HelpWidget::TOGGLE_HELP)->toBe('?')
        ->and(HelpWidget::QUIT)->toBe('q');
});

test('widget implements AppWidget interface', function () {
    $widget = new HelpWidget;

    expect($widget)->toBeInstanceOf(\Kantui\Contracts\AppWidget::class);
});
