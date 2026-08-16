<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Support;

use Spatie\Activitylog\Models\Activity;

/**
 * How the stored `description` is said on screen.
 *
 * Translated **on read**, against the config map: the row keeps the string it was written
 * with and nobody rewrites the past, which is the one thing an audit trail must not allow.
 * So the map also reaches old entries, and dropping it returns the screen to what the row
 * says. A description that is not in the map is shown verbatim.
 */
final class StoredDescription
{
    public static function of(Activity $activity): string
    {
        $stored = $activity->description;

        /** @var array<string, string> $translations */
        $translations = config('filament-activitylog.descriptions', []);

        return $translations[$stored] ?? $stored;
    }
}
