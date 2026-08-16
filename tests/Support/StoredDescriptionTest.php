<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Support\StoredDescription;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

pest()->extend(TestCase::class);

test('a description the map lists is read in the words the map gives it', function (): void {
    config()->set('filament-activitylog.descriptions', ['Cuenta creada' => 'Account created']);

    activity()->log('Cuenta creada');

    expect(StoredDescription::of(lastActivity()))->toBe('Account created');
});

test('a description the map does not list is read exactly as it was written', function (): void {
    config()->set('filament-activitylog.descriptions', ['Cuenta creada' => 'Account created']);

    activity()->log('Cuenta creada');
    $listed = lastActivity();

    activity()->log('Cuenta archivada');
    $unlisted = lastActivity();

    expect(StoredDescription::of($unlisted))->toBe('Cuenta archivada')
        ->and(StoredDescription::of($listed))->toBe('Account created');
});

test('a description is read as written when the map is empty', function (): void {
    config()->set('filament-activitylog.descriptions', []);

    activity()->log('Cuenta creada');

    expect(StoredDescription::of(lastActivity()))->toBe('Cuenta creada');
});

test('a description is read as written when no map is configured at all', function (): void {
    config()->set('filament-activitylog.descriptions');

    activity()->log('Cuenta creada');

    expect(StoredDescription::of(lastActivity()))->toBe('Cuenta creada');
});

test('the map reaches entries written long before it existed', function (): void {
    activity()->log('Cuenta creada');

    $written = lastActivity();

    expect(StoredDescription::of($written))->toBe('Cuenta creada');

    config()->set('filament-activitylog.descriptions', ['Cuenta creada' => 'Account created']);

    expect(StoredDescription::of($written))->toBe('Account created');
});

test('reading a description never rewrites the words the row keeps', function (): void {
    config()->set('filament-activitylog.descriptions', ['Cuenta creada' => 'Account created']);

    activity()->log('Cuenta creada');

    $activity = lastActivity();

    $read = StoredDescription::of($activity);

    $stored = Activity::query()->whereKey($activity->getKey())->firstOrFail();

    expect($read)->toBe('Account created')
        ->and($read)->not->toBe($activity->description)
        ->and($activity->description)->toBe('Cuenta creada')
        ->and($activity->isDirty())->toBeFalse()
        ->and($stored->description)->toBe('Cuenta creada');
});

test('a numeric description the map lists is read through the map all the same', function (): void {
    config()->set('filament-activitylog.descriptions', ['404' => 'Not found']);

    $activity = new Activity(['description' => 404]);

    expect(StoredDescription::of($activity))->toBe('Not found');
});

test('a description that is not a string is refused rather than quietly turned into one', function (): void {
    config()->set('filament-activitylog.descriptions', ['Cuenta creada' => 'Account created']);

    expect(fn (): string => StoredDescription::of(new Activity(['description' => 404])))
        ->toThrow(TypeError::class, 'Return value must be of type string, int returned')
        ->and(fn (): string => StoredDescription::of(new Activity))
        ->toThrow(TypeError::class, 'Return value must be of type string, null returned');
});
