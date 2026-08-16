<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Logging;

use ElPandaPe\FilamentActivitylog\Contracts\ResolvesCauserRole;
use ElPandaPe\FilamentActivitylog\Support\RecordName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Writes into every row the names its parties had at that instant.
 *
 * `subject_id` and `causer_id` are pointers, and a pointer to a deleted row answers
 * nothing: without this, deleting an administrator turns their whole history into
 * "System" — right about the person somebody deletes when there is something to
 * investigate. Proper names go in the row; the grammar stays in the code, so fixing a typo
 * never means rewriting the past.
 *
 * A missing key is information: it tells "there was no author" apart from "this row
 * predates the seal", which falls back to the relation.
 */
final class ActorSeal
{
    public static function stamp(Model $activity): void
    {
        $actors = array_filter([
            'subject' => self::nameOf($activity, 'subject'),
            'causer' => self::nameOf($activity, 'causer'),
            'causer_role' => self::causerRole($activity),
        ]);

        if ($actors !== []) {
            $properties = $activity->getAttribute('properties');

            $activity->setAttribute(
                'properties',
                ($properties instanceof Collection ? $properties : collect())->put('actors', $actors),
            );
        }
    }

    private static function nameOf(Model $activity, string $relation): ?string
    {
        if ($activity->getAttribute($relation.'_id') === null) {
            return null;
        }

        $actor = $activity->relationLoaded($relation) ? $activity->getRelation($relation) : null;

        return $actor instanceof Model ? RecordName::of($actor) : null;
    }

    private static function causerRole(Model $activity): ?string
    {
        $causer = $activity->getAttribute('causer_id') === null
            ? null
            : $activity->getRelation('causer');

        $resolver = self::roleResolver();

        return $causer instanceof Model && $resolver instanceof ResolvesCauserRole
            ? $resolver($causer)
            : null;
    }

    /**
     * The class named in the configuration, or null when there is none and when what it
     * names cannot answer the question.
     */
    private static function roleResolver(): ?ResolvesCauserRole
    {
        $resolver = config('filament-activitylog.logging.causer_role');

        $instance = is_string($resolver) && $resolver !== '' ? app($resolver) : null;

        return $instance instanceof ResolvesCauserRole ? $instance : null;
    }
}
