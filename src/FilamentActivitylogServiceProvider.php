<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog;

use ElPandaPe\FilamentActivitylog\Logging\BeforeLoggingHooks;
use Illuminate\Support\ServiceProvider;

final class FilamentActivitylogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-activitylog.php', 'filament-activitylog');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-activitylog');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-activitylog');

        $this->publishes([
            __DIR__.'/../config/filament-activitylog.php' => config_path('filament-activitylog.php'),
        ], 'filament-activitylog-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/filament-activitylog'),
        ], 'filament-activitylog-translations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-activitylog'),
        ], 'filament-activitylog-views');

        BeforeLoggingHooks::register();
    }
}
