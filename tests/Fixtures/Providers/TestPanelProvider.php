<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Providers;

use ElPandaPe\FilamentActivitylog\FilamentActivitylogPlugin;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\UserResource;
use Filament\Panel;
use Filament\PanelProvider;

final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('test')
            ->path('test')
            // The panel the package is built for: without it Filament opens wide any
            // resource whose model has no policy, and in silence.
            ->strictAuthorization()
            ->resources([
                UserResource::class,
            ])
            ->plugin(FilamentActivitylogPlugin::make());
    }
}
