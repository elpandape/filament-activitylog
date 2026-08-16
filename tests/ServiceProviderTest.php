<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\ActivityResource;
use ElPandaPe\FilamentActivitylog\FilamentActivitylogServiceProvider;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Exceptions\InvalidConfiguration;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Finder\SplFileInfo;

pest()->extend(TestCase::class);

test('a key the application never declared still answers with the package default', function (): void {
    expect(config('filament-activitylog.navigation.slug'))->toBe('activity')
        ->and(config('filament-activitylog.formats.time'))->toBe('H:i')
        ->and(config('filament-activitylog.per_page_options'))->toBe([10, 25, 50])
        ->and(config('filament-activitylog.timeline.limit'))->toBe(25)
        ->and(config('filament-activitylog.logging.seal_actors'))->toBeTrue()
        ->and(config('filament-activitylog.masked'))->toContain('password');
});

test('an application that declares part of the config keeps its own values and inherits the rest', function (): void {
    config()->set('filament-activitylog', ['timeline' => ['limit' => 3]]);

    $this->app->register(FilamentActivitylogServiceProvider::class, true);

    expect(config('filament-activitylog.timeline.limit'))->toBe(3)
        ->and(config('filament-activitylog.navigation.slug'))->toBe('activity')
        ->and(config('filament-activitylog.per_page_options'))->toBe([10, 25, 50]);
});

test('the package translations answer under their own namespace, in every language it ships', function (): void {
    expect(trans('filament-activitylog::ui.today'))->toBe('Today')
        ->and(trans('filament-activitylog::ui.columns.causer'))->toBe('By');

    App::setLocale('es');

    expect(trans('filament-activitylog::ui.today'))->toBe('Hoy')
        ->and(trans('filament-activitylog::ui.columns.causer'))->toBe('Autor');
});

test('every view the package ships resolves by name', function (): void {
    $names = collect(File::allFiles(dirname(__DIR__).'/resources/views'))
        ->map(static fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->filter(static fn (string $path): bool => str_ends_with($path, '.blade.php'))
        ->map(static fn (string $path): string => 'filament-activitylog::'.str_replace(
            DIRECTORY_SEPARATOR,
            '.',
            mb_substr($path, 0, -mb_strlen('.blade.php')),
        ))
        ->values()
        ->all();

    expect($names)->toContain(
        'filament-activitylog::actions.timeline.classic',
        'filament-activitylog::actions.timeline.thread',
        'filament-activitylog::infolists.changes',
        'filament-activitylog::infolists.context',
        'filament-activitylog::infolists.headline',
        'filament-activitylog::tables.causer',
        'filament-activitylog::tables.record',
    );

    foreach ($names as $name) {
        expect(View::exists($name))->toBeTrue();
    }
});

test('the views answer to the package namespace and to nothing else', function (): void {
    expect(View::exists('filament-activitylog::tables.record'))->toBeTrue()
        ->and(View::exists('filament-activitylog::tables.nothing-of-the-sort'))->toBeFalse()
        ->and(View::exists('tables.record'))->toBeFalse();
});

test('the config is publishable, and under a group of its own', function (): void {
    $paths = ServiceProvider::pathsToPublish(FilamentActivitylogServiceProvider::class, 'filament-activitylog-config');

    expect(ServiceProvider::publishableGroups())->toContain('filament-activitylog-config')
        ->and($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(dirname(__DIR__).'/config/filament-activitylog.php')
        ->and(array_values($paths))->toBe([config_path('filament-activitylog.php')]);
});

test('the language files are publishable, and under a group of their own', function (): void {
    $paths = ServiceProvider::pathsToPublish(FilamentActivitylogServiceProvider::class, 'filament-activitylog-translations');

    expect(ServiceProvider::publishableGroups())->toContain('filament-activitylog-translations')
        ->and($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(dirname(__DIR__).'/lang')
        ->and(array_values($paths))->toBe([lang_path('vendor/filament-activitylog')]);
});

test('the views are publishable, and under a group of their own', function (): void {
    $paths = ServiceProvider::pathsToPublish(FilamentActivitylogServiceProvider::class, 'filament-activitylog-views');

    expect(ServiceProvider::publishableGroups())->toContain('filament-activitylog-views')
        ->and($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(dirname(__DIR__).'/resources/views')
        ->and(array_values($paths))->toBe([resource_path('views/vendor/filament-activitylog')]);
});

test('the package publishes those three paths and no others', function (): void {
    expect(ServiceProvider::pathsToPublish(FilamentActivitylogServiceProvider::class))->toHaveCount(3);
});

test('a tag the package never registered publishes nothing', function (): void {
    expect(ServiceProvider::pathsToPublish(FilamentActivitylogServiceProvider::class, 'filament-activitylog-lang'))->toBeEmpty()
        ->and(ServiceProvider::pathsToPublish(FilamentActivitylogServiceProvider::class, 'filament-activitylog'))->toBeEmpty()
        ->and(ServiceProvider::publishableGroups())->not->toContain('filament-activitylog-lang')
        ->and(ServiceProvider::publishableGroups())->not->toContain('filament-activitylog');
});

test('the resource reads the model activitylog is configured with', function (): void {
    $subclass = new class extends Activity {};

    config()->set('activitylog.activity_model', $subclass::class);

    expect($subclass::class)->not->toBe(Activity::class)
        ->and(ActivityResource::getModel())->toBe($subclass::class);
});

test('the resource falls back to the shipped activity model when nothing names one', function (): void {
    config()->set('activitylog.activity_model');

    expect(ActivityResource::getModel())->toBe(Activity::class);
});

test('a configured model that is no activity is refused where the resource asks for it', function (): void {
    config()->set('activitylog.activity_model', User::class);

    expect(static fn (): string => ActivityResource::getModel())->toThrow(InvalidConfiguration::class);
});

test('booting the package hangs the before-logging hooks on the logger', function (): void {
    signIn('Rosa Chambi');
    makeUser('Tupac Mamani');

    $activity = lastActivity();

    expect(data_get($activity->getAttribute('attribute_changes'), 'attributes.name'))->toBe('Tupac Mamani')
        ->and(data_get($activity->getAttribute('attribute_changes'), 'attributes.password'))->toBe('*****')
        ->and(data_get($activity->getAttribute('properties'), 'actors.subject'))->toBe('Tupac Mamani')
        ->and(data_get($activity->getAttribute('properties'), 'actors.causer'))->toBe('Rosa Chambi');
});

test('the hooks are hung once, however many times the package boots', function (): void {
    $this->app->register(FilamentActivitylogServiceProvider::class, true);
    $this->app->register(FilamentActivitylogServiceProvider::class, true);

    $fromThisPackage = collect(Arr::wrap(new ReflectionProperty(LogActivityAction::class, 'beforeLoggingCallbacks')->getValue()))
        ->filter(static function (mixed $callback): bool {
            $file = $callback instanceof Closure ? new ReflectionFunction($callback)->getFileName() : null;

            return is_string($file) && str_ends_with($file, DIRECTORY_SEPARATOR.'Logging'.DIRECTORY_SEPARATOR.'BeforeLoggingHooks.php');
        });

    expect($fromThisPackage)->toHaveCount(1);
});
