<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\ActivityResource;
use ElPandaPe\FilamentActivitylog\FilamentActivitylogPlugin;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\UserResource;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Panel;

pest()->extend(TestCase::class);

test('the plugin answers to the id a panel files it under', function (): void {
    expect(FilamentActivitylogPlugin::make()->getId())->toBe('filament-activitylog')
        ->and(Filament::getPanel('test')->getPlugin('filament-activitylog'))
        ->toBeInstanceOf(FilamentActivitylogPlugin::class);
});

test('registering the plugin is what puts the activity resource on a panel', function (): void {
    $panel = Panel::make()->id('scratch');

    expect($panel->getResources())->not->toContain(ActivityResource::class);

    $panel->plugin(FilamentActivitylogPlugin::make());

    expect($panel->getResources())->toContain(ActivityResource::class)
        ->and($panel->getPlugin('filament-activitylog'))->toBeInstanceOf(FilamentActivitylogPlugin::class);
});

test('a panel that took the plugin serves the activity resource', function (): void {
    expect(Filament::getPanel('test')->getResources())
        ->toContain(ActivityResource::class)
        ->toContain(UserResource::class);
});

test('booting the plugin leaves the panel exactly as it found it', function (): void {
    $panel = Panel::make()->id('scratch');

    $panel->plugin(FilamentActivitylogPlugin::make());

    $resources = $panel->getResources();
    $plugins = $panel->getPlugins();

    $panel->getPlugin('filament-activitylog')->boot($panel);

    expect($panel->getResources())->toBe($resources)
        ->toContain(ActivityResource::class)
        ->and($panel->getPlugins())->toBe($plugins);
});

test('the plugin is built through the container, so an application can put its own in its place', function (): void {
    $substitute = new FilamentActivitylogPlugin;

    expect(FilamentActivitylogPlugin::make())->not->toBe($substitute);

    $this->app->instance(FilamentActivitylogPlugin::class, $substitute);

    expect(FilamentActivitylogPlugin::make())->toBe($substitute);
});
