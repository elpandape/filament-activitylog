<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Logging\BeforeLoggingHooks;
use ElPandaPe\FilamentActivitylog\Logging\SecretMask;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Spatie\Activitylog\Actions\LogActivityAction;

pest()->extend(TestCase::class);

test('the package hangs exactly one callback off the hook', function (): void {
    $hooks = new ReflectionProperty(LogActivityAction::class, 'beforeLoggingCallbacks')->getValue();

    expect($hooks)->toHaveCount(1);
});

test('asking for registration again does not stack a second callback', function (): void {
    BeforeLoggingHooks::register();
    BeforeLoggingHooks::register();

    $hooks = new ReflectionProperty(LogActivityAction::class, 'beforeLoggingCallbacks')->getValue();

    expect($hooks)->toHaveCount(1);
});

test('a re-registered hook still does its work, and does it once', function (): void {
    BeforeLoggingHooks::register();

    $user = makeUser('Amaru Quispe');
    $user->update(['name' => 'Amaru Yupanqui', 'password' => 'clave/con/barras']);

    $entry = lastActivity();
    $hooks = new ReflectionProperty(LogActivityAction::class, 'beforeLoggingCallbacks')->getValue();

    expect($hooks)->toHaveCount(1)
        ->and($entry->getProperty('actors'))->toBe(['subject' => 'Amaru Yupanqui'])
        ->and(data_get($entry->attribute_changes, 'attributes.password'))->toBe(SecretMask::MASK);
});

test('both hooks reach the same entry', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui', 'password' => 'clave/con/barras']);

    $entry = lastActivity();

    expect($entry->getProperty('actors'))->toBe([
        'subject' => 'Amaru Yupanqui',
        'causer' => 'Nayra Condori',
    ])->and(data_get($entry->attribute_changes, 'attributes'))->toBe([
        'name' => 'Amaru Yupanqui',
        'password' => SecretMask::MASK,
    ]);
});

test('an application that wants no seal gets entries without one', function (): void {
    config()->set('filament-activitylog.logging.seal_actors', false);

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    $entry = lastActivity();

    expect($entry->getProperty('actors'))->toBeNull()
        ->and($entry->causer_id)->not->toBeNull()
        ->and(data_get($entry->attribute_changes, 'attributes'))->toBe(['name' => 'Amaru Yupanqui']);
});

test('an application that wants no masking gets the secret written as it is', function (): void {
    config()->set('filament-activitylog.logging.mask_secrets', false);

    $user = makeUser('Amaru Quispe');

    $user->update(['password' => 'clave/con/barras']);

    $entry = lastActivity();
    $stored = (string) json_encode($entry->attribute_changes, JSON_UNESCAPED_SLASHES);

    expect(data_get($entry->attribute_changes, 'attributes.password'))->toBe('clave/con/barras')
        ->and($stored)->toContain('clave/con/barras')
        ->not->toContain(SecretMask::MASK);
});

test('an entry is written whole when both hooks are switched off', function (): void {
    config()->set('filament-activitylog.logging.seal_actors', false);
    config()->set('filament-activitylog.logging.mask_secrets', false);

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui', 'password' => 'clave/con/barras']);

    $entry = lastActivity();

    expect($entry->getProperty('actors'))->toBeNull()
        ->and(data_get($entry->attribute_changes, 'attributes'))->toBe([
            'name' => 'Amaru Yupanqui',
            'password' => 'clave/con/barras',
        ]);
});

test('the switches are read when an entry is written, not when the hook was registered', function (): void {
    config()->set('filament-activitylog.logging.mask_secrets', false);

    $user = makeUser('Amaru Quispe');
    $user->update(['password' => 'clave/con/barras']);

    expect(data_get(lastActivity()->attribute_changes, 'attributes.password'))->toBe('clave/con/barras');

    config()->set('filament-activitylog.logging.mask_secrets', true);

    $user->update(['password' => 'otra/clave/distinta']);

    expect(data_get(lastActivity()->attribute_changes, 'attributes.password'))->toBe(SecretMask::MASK);
});
