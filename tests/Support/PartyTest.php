<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Support\Party;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Roles\FixedCauserRole;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Filament\Support\Icons\Heroicon;

pest()->extend(TestCase::class);

test('the subject side answers with the record the entry is about', function (): void {
    signIn();
    $subject = makeUser('Amaru Quispe');
    $subject->update(['email' => 'amaru@example.test']);

    $party = Party::of(lastActivity()->load(['subject', 'causer']), 'subject');

    expect($party->isSubject)->toBeTrue()
        ->and($party->name)->toBe('Amaru Quispe')
        ->and($party->role)->toBeNull()
        ->and($party->type)->toBe('User')
        ->and($party->key)->toBeString()
        ->and($party->key)->toEqual($subject->getKey())
        ->and($party->url)->toBe('/test/users/'.$party->key)
        ->and($party->exists())->toBeTrue()
        ->and($party->initials())->toBe('AQ');
});

test('the causer side answers with who acted, and with no role until one is sealed', function (): void {
    $causer = signIn('Nayra Condori');
    makeUser('Amaru Quispe')->update(['email' => 'amaru@example.test']);

    $party = Party::of(lastActivity()->load(['subject', 'causer']), 'causer');

    expect($party->isSubject)->toBeFalse()
        ->and($party->name)->toBe('Nayra Condori')
        ->and($party->role)->toBeNull()
        ->and($party->type)->toBe('User')
        ->and($party->key)->toBeString()
        ->and($party->key)->toEqual($causer->getKey())
        ->and($party->url)->toBe('/test/users/'.$party->key)
        ->and($party->exists())->toBeTrue()
        ->and($party->initials())->toBe('NC');
});

test('the causer side carries the authority the entry was sealed with', function (): void {
    FixedCauserRole::$role = 'Super admin';
    config()->set('filament-activitylog.logging.causer_role', FixedCauserRole::class);

    signIn('Nayra Condori');
    makeUser('Amaru Quispe')->update(['email' => 'amaru@example.test']);

    $activity = lastActivity()->load(['subject', 'causer']);

    expect(Party::of($activity, 'causer')->role)->toBe('Super admin')
        ->and(Party::of($activity, 'subject')->role)->toBeNull();
});

test('a record the config says nothing about gets a neutral icon and the colour of its side', function (): void {
    signIn();
    makeUser()->update(['email' => 'amaru@example.test']);

    $activity = lastActivity()->load(['subject', 'causer']);

    expect(Party::of($activity, 'subject')->icon)->toBe(Heroicon::OutlinedCube)
        ->and(Party::of($activity, 'subject')->color)->toBe('info')
        ->and(Party::of($activity, 'causer')->icon)->toBe(Heroicon::OutlinedCube)
        ->and(Party::of($activity, 'causer')->color)->toBe('primary');
});

test('a record the config describes shows the icon and colour it was given on both sides', function (): void {
    signIn();
    makeUser()->update(['email' => 'amaru@example.test']);

    config()->set('filament-activitylog.records.'.User::class, [
        'icon' => Heroicon::OutlinedUser,
        'color' => 'danger',
    ]);

    $activity = lastActivity()->load(['subject', 'causer']);

    expect(Party::of($activity, 'subject')->icon)->toBe(Heroicon::OutlinedUser)
        ->and(Party::of($activity, 'subject')->color)->toBe('danger')
        ->and(Party::of($activity, 'causer')->icon)->toBe(Heroicon::OutlinedUser)
        ->and(Party::of($activity, 'causer')->color)->toBe('danger');
});

test('an icon given as the name of a blade icon is kept as it is', function (): void {
    signIn();
    makeUser()->update(['email' => 'amaru@example.test']);

    config()->set('filament-activitylog.records.'.User::class, ['icon' => 'heroicon-o-key']);

    expect(Party::of(lastActivity()->load(['subject', 'causer']), 'subject')->icon)->toBe('heroicon-o-key');
});

test('a colour is scrubbed of anything that could escape the custom property it lands in', function (): void {
    signIn();
    makeUser()->update(['email' => 'amaru@example.test']);

    config()->set('filament-activitylog.records.'.User::class, ['color' => 'danger); color: red']);

    $color = Party::of(lastActivity()->load(['subject', 'causer']), 'subject')->color;

    expect($color)->toBe('dangercolorred')
        ->and($color)->not->toContain(')')
        ->and($color)->not->toContain(':')
        ->and($color)->not->toContain(' ');
});

test('a records entry whose icon and colour are unusable falls back to the neutral pair', function (): void {
    signIn();
    makeUser()->update(['email' => 'amaru@example.test']);

    config()->set('filament-activitylog.records.'.User::class, ['icon' => 42, 'color' => 99]);

    $party = Party::of(lastActivity()->load(['subject', 'causer']), 'subject');

    expect($party->icon)->toBe(Heroicon::OutlinedCube)
        ->and($party->color)->toBe('gray')
        ->and($party->color)->not->toBe('info');
});

test('a key the row does not hold as a scalar is left out rather than forced into a string', function (): void {
    makeUser('Amaru Quispe');

    $activity = lastActivity()->load(['subject', 'causer']);
    $activity->setAttribute('subject_id', ['composite', 'key']);

    $party = Party::of($activity, 'subject');

    expect($activity->getAttribute('subject_id'))->toBeArray()
        ->and($party->key)->toBeNull()
        ->and($party->name)->toBe('Amaru Quispe')
        ->and($party->type)->toBe('User');
});

test('an entry nobody caused leaves the causer side empty while the subject side still answers', function (): void {
    makeUser('Amaru Quispe');

    $activity = lastActivity()->load(['subject', 'causer']);
    $party = Party::of($activity, 'causer');

    expect($party->name)->toBeNull()
        ->and($party->role)->toBeNull()
        ->and($party->type)->toBeNull()
        ->and($party->key)->toBeNull()
        ->and($party->url)->toBeNull()
        ->and($party->color)->toBe('primary')
        ->and($party->icon)->toBe(Heroicon::OutlinedCube)
        ->and($party->exists())->toBeFalse()
        ->and($party->initials())->toBeEmpty()
        ->and(Party::of($activity, 'subject')->exists())->toBeTrue();
});

test('an entry about no record in particular leaves the subject side empty while the causer side still answers', function (): void {
    signIn('Nayra Condori');

    activity()->event('signed in')->log('Signed in');

    $activity = lastActivity()->load(['subject', 'causer']);
    $party = Party::of($activity, 'subject');

    expect($party->name)->toBeNull()
        ->and($party->type)->toBeNull()
        ->and($party->key)->toBeNull()
        ->and($party->url)->toBeNull()
        ->and($party->color)->toBe('info')
        ->and($party->exists())->toBeFalse()
        ->and($party->initials())->toBeEmpty()
        ->and(Party::of($activity, 'causer')->exists())->toBeTrue();
});
