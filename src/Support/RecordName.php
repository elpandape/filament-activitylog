<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * The name a record goes by, read from the attribute its model is configured with.
 */
final class RecordName
{
    public static function of(Model $record): ?string
    {
        $name = $record->getAttributes()[self::attribute($record)] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    private static function attribute(Model $record): string
    {
        $attribute = config('filament-activitylog.records.'.$record->getMorphClass().'.name');

        return is_string($attribute) && $attribute !== '' ? $attribute : 'name';
    }
}
