<?php

use Kantui\Contracts\AppWidget;
use Kantui\Widgets\HelpWidget;
use PhpTui\Tui\Widget\Widget;

test('AppWidget interface defines required methods', function () {
    $reflection = new ReflectionClass(AppWidget::class);

    expect($reflection->hasMethod('render'))->toBeTrue()
        ->and($reflection->hasMethod('getFooterText'))->toBeTrue()
        ->and($reflection->hasMethod('handleCharKey'))->toBeTrue()
        ->and($reflection->hasMethod('handleCodedKey'))->toBeTrue();
});

test('HelpWidget implements AppWidget interface', function () {
    $widget = new HelpWidget;

    expect($widget)->toBeInstanceOf(AppWidget::class);
});

test('HelpWidget render returns Widget', function () {
    $widget = new HelpWidget;
    $rendered = $widget->render();

    expect($rendered)->toBeInstanceOf(Widget::class);
});

test('HelpWidget getFooterText returns string', function () {
    $widget = new HelpWidget;
    $footer = $widget->getFooterText();

    expect($footer)->toBeString()
        ->and(strlen($footer))->toBeGreaterThan(0);
});

test('AppWidget contract enforces return type for render', function () {
    $reflection = new ReflectionClass(AppWidget::class);
    $method = $reflection->getMethod('render');
    $returnType = $method->getReturnType();

    expect($returnType->getName())->toBe(Widget::class);
});

test('AppWidget contract enforces return type for getFooterText', function () {
    $reflection = new ReflectionClass(AppWidget::class);
    $method = $reflection->getMethod('getFooterText');
    $returnType = $method->getReturnType();

    expect($returnType->getName())->toBe('string');
});

test('AppWidget contract enforces parameter types for handleCharKey', function () {
    $reflection = new ReflectionClass(AppWidget::class);
    $method = $reflection->getMethod('handleCharKey');
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getType()->getName())->toBe('PhpTui\Term\Event\CharKeyEvent');
});

test('AppWidget contract enforces parameter types for handleCodedKey', function () {
    $reflection = new ReflectionClass(AppWidget::class);
    $method = $reflection->getMethod('handleCodedKey');
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getType()->getName())->toBe('PhpTui\Term\Event\CodedKeyEvent');
});

test('HelpWidget has all required keybinding constants', function () {
    expect(defined('Kantui\Widgets\HelpWidget::MOVE_DOWN'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::MOVE_UP'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::MOVE_LEFT'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::MOVE_RIGHT'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::PROGRESS_ITEM'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::REGRESS_ITEM'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::NEW_ITEM'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::EDIT_ITEM'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::DELETE_ITEM'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::MOVE_ITEM_UP'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::MOVE_ITEM_DOWN'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::SEARCH'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::FILTER_URGENCY'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::CLEAR_FILTERS'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::TOGGLE_HELP'))->toBeTrue()
        ->and(defined('Kantui\Widgets\HelpWidget::QUIT'))->toBeTrue();
});

test('HelpWidget footer text mentions help close keys', function () {
    $widget = new HelpWidget;
    $footer = $widget->getFooterText();

    expect($footer)->toContain('?')
        ->and($footer)->toContain('q')
        ->and($footer)->toContain('ESC');
});
