<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Support;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Spatie\Activitylog\Models\Activity;

/**
 * How an event is recognised at a glance: its colour, its shape and its name.
 *
 * Colour and glyph travel together on purpose: `created` and `deleted` are exactly the pair
 * a colour-blind reader confuses, so the shape is what really tells them apart.
 */
final readonly class EventGlyph
{
    public function __construct(
        public string $color,
        public string|BackedEnum $icon,
        public string $label,
        public bool $minor,
    ) {}

    public static function of(Activity $activity): self
    {
        $event = $activity->event;

        [$color, $icon, $minor] = match ($event) {
            'created' => ['success', Heroicon::OutlinedPlusCircle, false],
            'updated' => ['info', Heroicon::OutlinedArrowPath, true],
            'deleted' => ['danger', Heroicon::OutlinedTrash, false],
            'restored' => ['warning', Heroicon::OutlinedArrowUturnLeft, false],
            default => ['gray', Heroicon::OutlinedBolt, false],
        };

        return new self(
            color: $color,
            icon: $icon,
            label: is_string($event) && $event !== '' ? self::label($event) : __('filament-activitylog::ui.detail.no_event'),
            minor: $minor,
        );
    }

    /**
     * The four events the model writes are translated; anything the application named is its
     * own word and is shown as it was written. Without this the event filter offered one
     * language and the rows it selected read in another.
     */
    private static function label(string $event): string
    {
        return match ($event) {
            'created' => __('filament-activitylog::ui.events.created'),
            'updated' => __('filament-activitylog::ui.events.updated'),
            'deleted' => __('filament-activitylog::ui.events.deleted'),
            'restored' => __('filament-activitylog::ui.events.restored'),
            default => $event,
        };
    }
}
