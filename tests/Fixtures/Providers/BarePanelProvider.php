<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Providers;

use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\UserResource;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\PanelRegistry;

/**
 * A panel that never installs the plugin: the ordinary case of an application that hangs the
 * activity drawer on one of its records without also publishing the activity log resource.
 *
 * Deliberately not `->default()`. `TestCase::setUp()` points Filament at `test`, and a second
 * default would decide which panel every other test in the suite runs against.
 */
final class BarePanelProvider extends PanelProvider
{
    /**
     * Raises the panel from inside a test, which the ordinary provider route cannot do here.
     *
     * `Filament::registerPanel()` only queues the panel against the next resolution of the
     * `PanelRegistry`, and that registry is a singleton the application resolved long before
     * a test runs — so a provider registered this late never reaches it. The registry is
     * therefore handed the panel directly.
     *
     * The routes are then raised by running Filament's own route file again: it loops over
     * the panels registered by the time Filament boots, and this one arrives after that.
     * They are not optional — every page in a panel asks its resource for the index route to
     * build a breadcrumb, so without them the page dies before the drawer is reached.
     */
    public static function install(): void
    {
        app(PanelRegistry::class)->register(
            new self(app())->panel(Panel::make()),
        );

        require dirname(__DIR__, 3).'/vendor/filament/filament/routes/web.php';
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('bare')
            ->path('bare')
            // The same posture as the panel the package is built for, so what this fixture
            // proves is the missing plugin and not a laxer panel.
            ->strictAuthorization()
            ->resources([
                UserResource::class,
            ]);
    }
}
