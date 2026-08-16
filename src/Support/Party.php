<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Support;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Spatie\Activitylog\Models\Activity;

/**
 * One of the two sides of an entry, resolved once and ready to render.
 *
 * The detail page asks the same questions from several places —who, with what authority,
 * over what, and where to go from here— and answering them per view is how two screens end
 * up telling the same fact differently. Nothing here is inferred: name and role come from
 * the seal the application wrote into the row, which is why a deleted side still has a name.
 */
final readonly class Party
{
    public function __construct(
        public bool $isSubject,
        public ?string $name,
        public ?string $role,
        public ?string $type,
        public ?string $key,
        public ?string $url,
        public string|BackedEnum $icon,
        public string $color,
    ) {}

    public static function of(Activity $activity, string $side): self
    {
        $isSubject = $side === 'subject';

        $type = $isSubject ? $activity->subject_type : $activity->causer_type;
        $key = $isSubject ? $activity->subject_id : $activity->causer_id;

        /** @var array<string, mixed> $style */
        $style = is_string($type) ? config('filament-activitylog.records.'.$type, []) : [];

        $icon = $style['icon'] ?? null;
        $color = $style['color'] ?? ($isSubject ? 'info' : 'primary');

        return new self(
            isSubject: $isSubject,
            name: $isSubject ? Narrative::subjectName($activity) : Narrative::causerName($activity),
            role: $isSubject ? null : Narrative::causerRole($activity),
            type: is_string($type) ? class_basename($type) : null,
            key: is_scalar($key) ? (string) $key : null,
            url: RecordUrl::for($activity->getAttribute($side)),
            icon: $icon instanceof BackedEnum || is_string($icon) ? $icon : Heroicon::OutlinedCube,
            color: preg_replace('/[^a-z0-9_-]/i', '', is_string($color) ? $color : 'gray') ?? 'gray',
        );
    }

    public function exists(): bool
    {
        return $this->name !== null;
    }

    public function initials(): string
    {
        return $this->name === null ? '' : Narrative::initials($this->name);
    }
}
