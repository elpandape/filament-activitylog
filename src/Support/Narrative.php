<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * An activity entry told as a sentence.
 *
 * Names come from `properties.actors` when the application sealed them into the row, and
 * fall back to the relation only when they are missing: that is what lets an entry about a
 * deleted account still name it. This is also the only place in the package that emits HTML
 * from stored data, so every value goes through `e()`.
 */
final class Narrative
{
    /**
     * Beyond this many fields, listing them says nothing the detail page does not say better.
     */
    private const int FIELDS_NAMED = 2;

    public static function sentence(Activity $record): HtmlString
    {
        $causer = self::causerName($record);
        $subject = self::subjectName($record);
        $verb = self::verb($record);

        if ($verb === null || $subject === null) {
            return new HtmlString($causer === null
                ? e($record->description)
                : self::strong($causer).' — '.e($record->description));
        }

        $fields = self::fieldList($record);

        $doneToTheRecord = self::isModelEvent($record);

        if ($causer === null) {
            return new HtmlString(self::strong($subject).' '.($doneToTheRecord ? 'was ' : '').$verb.($fields === null
                ? ''
                : ' ('.e($fields).')'));
        }

        $actor = self::strong($causer);

        if (self::actedOnThemselves($record)) {
            if (! $doneToTheRecord) {
                return new HtmlString($actor.' '.$verb);
            }

            return new HtmlString($verb === 'updated' && $fields !== null
                ? $actor.' changed their own '.e($fields)
                : $actor.' '.$verb.' their own record');
        }

        return new HtmlString($verb === 'updated' && $fields !== null
            ? $actor.' changed the '.e($fields).' of '.self::strong($subject)
            : $actor.' '.$verb.' '.self::strong($subject));
    }

    /**
     * Who acted, or null when nobody did: a command, a job, a deployment script. That is
     * not missing data, and the sentence says so by changing voice.
     */
    public static function causerName(Activity $record): ?string
    {
        return self::sealed($record, 'causer')
            ?? self::fromRelation($record, 'causer');
    }

    /**
     * The role the causer acted with, if the application sealed it. The package never
     * infers it: a role held today does not say with what authority someone acted then.
     */
    public static function causerRole(Activity $record): ?string
    {
        return self::sealed($record, 'causer_role');
    }

    /**
     * `Str::substr` and not `substr`: a name may start with a multi-byte character.
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', mb_trim($name)) ?: [];

        $letters = array_map(
            static fn (string $word): string => Str::upper(Str::substr($word, 0, 1)),
            array_slice(array_filter($words), 0, 2),
        );

        return implode('', $letters);
    }

    public static function subjectName(Activity $record): ?string
    {
        $name = self::sealed($record, 'subject')
            ?? self::fromRelation($record, 'subject');

        if ($name !== null) {
            return $name;
        }

        $type = $record->subject_type;

        return is_string($type) && $record->subject_id !== null
            ? class_basename($type).' #'.$record->subject_id
            : null;
    }

    /**
     * A masked field is **named** but never valued: hiding the change would lie, showing
     * the value would leak it.
     *
     * @return list<array{field: string, old: ?string, new: ?string, masked: bool}>
     */
    public static function amendments(Activity $record): array
    {
        $new = data_get($record->attribute_changes, 'attributes');
        $old = data_get($record->attribute_changes, 'old');

        $new = is_array($new) ? $new : [];
        $old = is_array($old) ? $old : [];

        /** @var array<int, int|string> $fields */
        $fields = array_keys($new + $old);

        return array_values(array_map(static function (int|string $field) use ($new, $old): array {
            $name = (string) $field;
            $masked = self::isMasked($name);

            return [
                'field' => Str::headline($name),
                'old' => $masked ? null : self::readable($old[$name] ?? null),
                'new' => $masked ? null : self::readable($new[$name] ?? null),
                'masked' => $masked,
            ];
        }, $fields));
    }

    public static function isMasked(string $attribute): bool
    {
        /** @var array<int, string> $masked */
        $masked = config('filament-activitylog.masked', []);

        return in_array($attribute, $masked, true);
    }

    private static function readable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value)
            ? (string) $value
            : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);
    }

    private static function sealed(Activity $record, string $actor): ?string
    {
        $name = data_get($record->properties, 'actors.'.$actor);

        return is_string($name) && $name !== '' ? $name : null;
    }

    private static function fromRelation(Activity $record, string $relation): ?string
    {
        $actor = $record->getAttribute($relation);

        if (! $actor instanceof Model) {
            return null;
        }

        $name = RecordName::of($actor);

        $key = $actor->getKey();

        return $name ?? class_basename($actor).' #'.(is_scalar($key) ? $key : '?');
    }

    /**
     * The event **is** the verb, so a hand-written entry should carry one: without an event
     * there is no sentence to compose, only the description someone wrote.
     */
    private static function verb(Activity $record): ?string
    {
        $event = $record->event;

        return is_string($event) && $event !== ''
            ? Str::lower(Str::headline($event))
            : null;
    }

    private static function isModelEvent(Activity $record): bool
    {
        return in_array($record->event, ['created', 'updated', 'deleted', 'restored'], true);
    }

    /**
     * Null when there is no field to name, or more than FIELDS_NAMED of them. Fields are
     * named, never valued: one of them may be a password.
     */
    private static function fieldList(Activity $record): ?string
    {
        $attributes = data_get($record->attribute_changes, 'attributes');

        $fields = is_array($attributes) ? array_keys($attributes) : [];

        if ($fields === [] || count($fields) > self::FIELDS_NAMED) {
            return null;
        }

        $readable = array_map(
            static fn (int|string $field): string => Str::lower(Str::headline((string) $field)),
            $fields,
        );

        return implode(' and ', $readable);
    }

    private static function actedOnThemselves(Activity $record): bool
    {
        return $record->causer_type === $record->subject_type
            && $record->causer_id !== null
            && $record->causer_id === $record->subject_id;
    }

    private static function strong(string $value): string
    {
        return '<strong>'.e($value).'</strong>';
    }
}
