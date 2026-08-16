<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Logging\SecretMask;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Illuminate\Support\Facades\Hash;

pest()->extend(TestCase::class);

test('a password never reaches the database, on either side of the change', function (): void {
    $user = makeUser('Amaru Quispe');
    $previous = Hash::make('clave-de-ayer');
    $user->update(['password' => $previous]);
    $current = Hash::make('clave-de-hoy');

    $user->update(['password' => $current]);

    $entry = lastActivity();
    $stored = (string) json_encode($entry->attribute_changes, JSON_UNESCAPED_SLASHES);

    expect(data_get($entry->attribute_changes, 'attributes.password'))->toBe(SecretMask::MASK)
        ->and(data_get($entry->attribute_changes, 'old.password'))->toBe(SecretMask::MASK)
        ->and($stored)
        ->not->toContain($current)
        ->not->toContain($previous)
        ->toContain(SecretMask::MASK);
});

test('a secret full of slashes is gone from the row and not merely escaped in it', function (): void {
    $user = makeUser('Amaru Quispe');

    $user->update(['password' => 'clave/con/barras']);

    $stored = (string) json_encode(lastActivity()->attribute_changes, JSON_UNESCAPED_SLASHES);

    expect($stored)
        ->not->toContain('clave/con/barras')
        ->toContain(SecretMask::MASK);
});

test('the entry still says the secret changed', function (): void {
    $user = makeUser('Amaru Quispe');

    $user->update(['password' => Hash::make('clave-de-hoy')]);

    expect(data_get(lastActivity()->attribute_changes, 'attributes'))
        ->toBe(['password' => SecretMask::MASK]);
});

test('an attribute nobody asked to hide keeps its value', function (): void {
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    $entry = lastActivity();

    expect(data_get($entry->attribute_changes, 'attributes'))->toBe(['name' => 'Amaru Yupanqui'])
        ->and(data_get($entry->attribute_changes, 'old'))->toBe(['name' => 'Amaru Quispe']);
});

test('what is hidden is whatever the application listed', function (): void {
    config()->set('filament-activitylog.masked', ['name']);

    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui', 'password' => 'clave-en-claro']);

    expect(data_get(lastActivity()->attribute_changes, 'attributes'))
        ->toBe(['name' => SecretMask::MASK, 'password' => 'clave-en-claro']);
});

test('a secret is hidden the moment a record is created, where there is no old half', function (): void {
    $user = makeUser('Amaru Quispe');

    $entry = lastActivity();

    expect($entry->event)->toBe('created')
        ->and($entry->subject_id)->toBe($user->getKey())
        ->and(data_get($entry->attribute_changes, 'attributes.password'))->toBe(SecretMask::MASK)
        ->and(data_get($entry->attribute_changes, 'attributes.name'))->toBe('Amaru Quispe')
        ->and(data_get($entry->attribute_changes, 'old'))->toBeNull();
});

test('a secret is hidden when a record is deleted, where the old half is all there is', function (): void {
    $user = makeUser('Amaru Quispe');

    $user->delete();

    $entry = lastActivity();

    expect($entry->event)->toBe('deleted')
        ->and(data_get($entry->attribute_changes, 'old.password'))->toBe(SecretMask::MASK)
        ->and(data_get($entry->attribute_changes, 'old.name'))->toBe('Amaru Quispe')
        ->and(data_get($entry->attribute_changes, 'attributes'))->toBeNull();
});

test('an entry that changed nothing is written unharmed', function (): void {
    signIn('Nayra Condori');

    activity()->event('signed in')->log('Signed in');

    $entry = lastActivity();

    expect($entry->description)->toBe('Signed in')
        ->and($entry->attribute_changes)->toBeEmpty();
});

test('a half that is not a set of attributes is left exactly as it came', function (): void {
    activity()
        ->event('imported')
        ->withChanges(['attributes' => 'nada que ver'])
        ->log('Imported');

    expect(data_get(lastActivity()->attribute_changes, 'attributes'))->toBe('nada que ver');
});
