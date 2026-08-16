<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Logging;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Replaces the value of every masked attribute before the row is written.
 *
 * This is what lets a model log an attribute it cannot store — a password — and still be
 * honest: the row says the attribute changed, and says nothing more. Replacing rather than
 * dropping is deliberate; dropping the key would hide the very fact worth recording.
 *
 * It runs on both write paths, the model trait and the `activity()` helper, so it also
 * covers vendor models, which cannot compose anything.
 */
final class SecretMask
{
    public const string MASK = '*****';

    public static function apply(Model $activity): void
    {
        $changes = $activity->getAttribute('attribute_changes');

        $activity->setAttribute(
            'attribute_changes',
            $changes instanceof Collection ? self::maskEachHalf($changes) : $changes,
        );
    }

    /**
     * Both halves alike: `old` holds the previous value, which is as secret as the new one,
     * and a creation only has `attributes`.
     *
     * @param  Collection<array-key, mixed>  $changes
     * @return Collection<array-key, mixed>
     */
    private static function maskEachHalf(Collection $changes): Collection
    {
        return $changes->map(static fn (mixed $half): mixed => is_array($half) ? self::mask($half) : $half);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<array-key, mixed>
     */
    private static function mask(array $attributes): array
    {
        /** @var array<int, string> $secrets */
        $secrets = config('filament-activitylog.masked', []);

        foreach ($secrets as $secret) {
            if (array_key_exists($secret, $attributes)) {
                $attributes[$secret] = self::MASK;
            }
        }

        return $attributes;
    }
}
