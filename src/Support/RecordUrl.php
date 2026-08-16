<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Support;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Where one side of an entry leads, if it leads anywhere.
 *
 * The package knows no routes: it asks the panel, which knows the resource behind each
 * model. Null —the side is then rendered without a link— when the record is gone, no
 * resource shows it, that resource has no view page, or the viewer may not see it.
 */
final class RecordUrl
{
    /**
     * Takes `mixed` because the caller passes a `MorphTo` relation, which is null when the
     * record is gone and anything at all when the stored `*_type` no longer loads.
     */
    public static function for(mixed $record): ?string
    {
        if (! $record instanceof Model) {
            return null;
        }

        $resource = Filament::getModelResource($record);

        if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
            return null;
        }

        if (! $resource::hasPage('view') || ! $resource::canView($record)) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $record], isAbsolute: false);
    }
}
