<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Logging;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Support\PendingActivityLog;

/**
 * Hangs what this package does to a row off `beforeLogging`, which runs inside `execute()`
 * before the row is saved.
 *
 * A hook and not a replacement for `activitylog.actions.log_activity`: an application that
 * already swapped that class keeps it, and both behaviours still apply.
 */
final class BeforeLoggingHooks
{
    /**
     * The callbacks live in a static array on the action, so they outlive the application
     * that registered them: without this guard a test suite booting fifty applications
     * would stack fifty identical callbacks. Each behaviour reads its own switch when it
     * runs, so one registration serves whatever configuration is current.
     */
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PendingActivityLog::beforeLogging(static function (Activity $activity): void {
            if ($activity instanceof Model) {
                self::applyTo($activity);
            }
        });
    }

    private static function applyTo(Model $activity): void
    {
        if (config('filament-activitylog.logging.mask_secrets', true) === true) {
            SecretMask::apply($activity);
        }

        if (config('filament-activitylog.logging.seal_actors', true) === true) {
            ActorSeal::stamp($activity);
        }
    }
}
