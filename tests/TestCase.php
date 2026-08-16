<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Carbon\Laravel\ServiceProvider as CarbonServiceProvider;
use ElPandaPe\FilamentActivitylog\FilamentActivitylogServiceProvider;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Policies\ActivityPolicy;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Providers\TestPanelProvider;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Roles\FixedCauserRole;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\QueryBuilder\QueryBuilderServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as ApplicationTestCase;
use Spatie\Activitylog\Models\Activity;

/**
 * The boot every test file asks for with `pest()->extend(TestCase::class)`, which resolves
 * the file calling it — hence no `->in(...)` anywhere in `tests/Pest.php`.
 *
 * What follows are traps already paid for: read the comment before simplifying the line.
 */
abstract class TestCase extends ApplicationTestCase
{
    use RefreshDatabase;

    /** @var Application */
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();

        // The applications this package is built for run Eloquent strictly, and the package
        // reads two `MorphTo` relations on every screen: without this the suite tests a
        // laxer world than the one it ships into.
        Model::shouldBeStrict();

        // No request has been through the panel's middleware here, so nothing has told
        // Filament which panel it is serving. Without this every resource resolves against
        // no panel at all and its pages refuse to mount.
        Filament::setCurrentPanel('test');
        Filament::bootCurrentPanel();

        // The fixture policies answer from static properties so a test can deny a screen;
        // they outlive the application, so they are put back before each test.
        ActivityPolicy::$viewAny = true;
        ActivityPolicy::$view = true;
        Fixtures\Policies\UserPolicy::$viewAny = true;
        Fixtures\Policies\UserPolicy::$view = true;
        FixedCauserRole::$role = 'Super admin';
    }

    /**
     * Filament has to register before Livewire: `SupportServiceProvider` binds `DataStore`
     * with an unshared `bind()` that overwrites Livewire's instance, so every
     * `store($component)->set(...)` is lost and the render dies on a null error bag.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentActivitylogServiceProvider::class,
            ActionsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            QueryBuilderServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            LivewireServiceProvider::class,
            // Carbon does not follow `App::setLocale()` on its own; a consuming application
            // gets this provider auto-discovered, and without it here the suite would pin
            // dates production never renders.
            CarbonServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    /** @param  Application  $app */
    protected function getEnvironmentSetUp($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $config->set('app.key', 'base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF/0kOO7HH+Z8=');

        $config->set('app.locale', 'en');
        $config->set('app.fallback_locale', 'en');

        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('queue.default', 'sync');

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $config->set('auth.defaults.guard', 'web');
        $config->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $config->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);

        // The model lives in vendor, where Laravel's policy guessing never looks for one.
        // This is the registration the package asks every application to make.
        Gate::policy(Activity::class, ActivityPolicy::class);
    }

    /**
     * Spatie ships its migration as a publishable stub and never loads it from vendor, so
     * the table is raised here by requiring the file and calling `up()` by hand.
     *
     * The hook is `defineDatabaseMigrationsAfterDatabaseRefreshed()` and not
     * `defineDatabaseMigrations()`: testbench calls the second *before* refreshing the
     * database, and with sqlite in memory the refresh raises an empty one over everything
     * created there. The symptom is a `no such table` halfway through the suite.
     */
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        /** @var Migration $migration */
        $migration = require dirname(__DIR__).'/vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub';

        $migration->up(); // @phpstan-ignore method.notFound

        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        // The shape a roles package leaves behind, which is what the default role resolver
        // reads: a table of roles and a pivot, and nothing of any particular vendor.
        Schema::create('roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
        });

        Schema::create('role_user', static function (Blueprint $table): void {
            $table->foreignId('role_id');
            $table->foreignId('user_id');
        });

        // Named by `title` rather than `name`, and shown by no resource: this is what the
        // suite points at to reach the configured naming attribute and the cases where a
        // party has nowhere to link to.
        Schema::create('articles', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }
}
