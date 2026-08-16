<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog;

use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\ActivityResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentActivitylogPlugin implements Plugin
{
    public static function make(): self
    {
        return resolve(self::class);
    }

    public function getId(): string
    {
        return 'filament-activitylog';
    }

    /**
     * Registering the resource is also what puts it on any permission catalogue the panel
     * derives from its components, so access to the activity log stays an ordinary row.
     */
    public function register(Panel $panel): void
    {
        $panel->resources([
            ActivityResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
