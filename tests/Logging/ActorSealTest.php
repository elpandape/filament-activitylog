<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Logging\SecretMask;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\Article;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Roles\FixedCauserRole;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

pest()->extend(TestCase::class);

test('an entry writes down the names the record and the author had at that instant', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    expect(lastActivity()->getProperty('actors'))->toBe([
        'subject' => 'Amaru Yupanqui',
        'causer' => 'Nayra Condori',
    ]);
});

test('the name a record had outlives the record itself', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');
    $user->update(['name' => 'Amaru Yupanqui']);

    $entry = lastActivity();

    $user->delete();

    $survivor = Activity::query()->whereKey($entry->getKey())->firstOrFail();

    expect($survivor->getProperty('actors.subject'))->toBe('Amaru Yupanqui')
        ->and($survivor->subject_id)->toBe($user->getKey())
        ->and(User::query()->find($survivor->subject_id))->toBeNull();
});

test('the name the author had outlives the author', function (): void {
    $causer = signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');
    $user->update(['name' => 'Amaru Yupanqui']);

    $entry = lastActivity();

    $causer->delete();

    $survivor = Activity::query()->whereKey($entry->getKey())->firstOrFail();

    expect($survivor->getProperty('actors.causer'))->toBe('Nayra Condori')
        ->and($survivor->causer_id)->toBe($causer->getKey())
        ->and(User::query()->find($survivor->causer_id))->toBeNull();
});

test('an entry nobody caused leaves the author out instead of naming nobody', function (): void {
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    $entry = lastActivity();

    expect($entry->causer_id)->toBeNull()
        ->and($entry->getProperty('actors'))->toBe(['subject' => 'Amaru Yupanqui']);
});

test('an author the row says is absent is not named from the relation left in memory', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    activity()
        ->performedOn($user)
        ->causedByAnonymous()
        ->event('signed in')
        ->log('Signed in');

    $entry = lastActivity();

    expect($entry->causer_id)->toBeNull()
        ->and($entry->getProperty('actors'))->toBe(['subject' => 'Amaru Quispe']);
});

test('the seal takes the actors key for itself and leaves the rest of the properties alone', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    activity()
        ->performedOn($user)
        ->event('updated')
        ->withProperties([
            'actors' => ['subject' => 'Written by the application', 'causer' => 'Written by the application'],
            'ip' => '10.0.0.1',
        ])
        ->log('Updated');

    $entry = lastActivity();

    expect($entry->getProperty('actors'))->toBe([
        'subject' => 'Amaru Quispe',
        'causer' => 'Nayra Condori',
    ])
        ->and($entry->getProperty('ip'))->toBe('10.0.0.1')
        ->and((string) json_encode($entry->properties))->not->toContain('Written by the application');
});

test('an entry the seal has nothing to say about keeps the actors the application wrote', function (): void {
    activity()
        ->event('imported')
        ->withProperties(['actors' => ['subject' => 'Written by the application']])
        ->log('Imported');

    expect(lastActivity()->getProperty('actors'))->toBe(['subject' => 'Written by the application']);
});

test('a secret handed to the entry as a property is written as it came, unlike the same one in the changes', function (): void {
    $user = makeUser('Amaru Quispe');

    activity()
        ->performedOn($user)
        ->event('updated')
        ->withProperties(['two_factor_secret' => 'JBSWY3DPEHPK3PXP'])
        ->withChanges(['attributes' => ['two_factor_secret' => 'JBSWY3DPEHPK3PXP']])
        ->log('Updated');

    $entry = lastActivity();

    expect($entry->getProperty('two_factor_secret'))->toBe('JBSWY3DPEHPK3PXP')
        ->and((string) json_encode($entry->properties))->toContain('JBSWY3DPEHPK3PXP')
        ->and(data_get($entry->attribute_changes, 'attributes.two_factor_secret'))->toBe(SecretMask::MASK);
});

test('every secret the shipped defaults name is hidden, not the password alone', function (): void {
    $user = makeUser('Amaru Quispe');

    activity()
        ->performedOn($user)
        ->event('updated')
        ->withChanges([
            'attributes' => [
                'name' => 'Amaru Yupanqui',
                'password' => 'clave-de-hoy',
                'remember_token' => 'DZmVKtzKPEcJn2wq0aDp',
                'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
                'two_factor_recovery_codes' => 'X4T9-2QLM',
            ],
            'old' => ['two_factor_secret' => 'MFRGGZDFMZTWQ2LK'],
        ])
        ->log('Updated');

    $entry = lastActivity();
    $stored = (string) json_encode($entry->attribute_changes);

    expect(data_get($entry->attribute_changes, 'attributes'))->toBe([
        'name' => 'Amaru Yupanqui',
        'password' => SecretMask::MASK,
        'remember_token' => SecretMask::MASK,
        'two_factor_secret' => SecretMask::MASK,
        'two_factor_recovery_codes' => SecretMask::MASK,
    ])
        ->and(data_get($entry->attribute_changes, 'old.two_factor_secret'))->toBe(SecretMask::MASK)
        ->and($stored)
        ->not->toContain('DZmVKtzKPEcJn2wq0aDp')
        ->not->toContain('JBSWY3DPEHPK3PXP')
        ->not->toContain('MFRGGZDFMZTWQ2LK')
        ->not->toContain('X4T9-2QLM');
});

test('a record is named by the attribute its model is configured with', function (): void {
    config()->set('filament-activitylog.records.'.Article::class.'.name', 'title');

    $article = Article::query()->create(['title' => 'La casa de carton']);

    activity()->performedOn($article)->event('published')->log('Article published');

    expect(lastActivity()->getProperty('actors'))->toBe(['subject' => 'La casa de carton']);
});

test('an entry about a nameless record with no author is sealed with nothing at all', function (): void {
    $article = Article::query()->create(['title' => 'La casa de carton']);

    activity()->performedOn($article)->event('published')->log('Article published');

    $entry = lastActivity();

    expect($entry->getProperty('actors'))->toBeNull()
        ->and($entry->subject_id)->toBe($article->getKey())
        ->and($entry->event)->toBe('published');
});

test('the role the author acted with is sealed alongside their name', function (): void {
    config()->set('filament-activitylog.logging.causer_role', FixedCauserRole::class);
    FixedCauserRole::$role = 'Super admin';

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    expect(lastActivity()->getProperty('actors'))->toBe([
        'subject' => 'Amaru Yupanqui',
        'causer' => 'Nayra Condori',
        'causer_role' => 'Super admin',
    ]);
});

test('the role sealed is the one the author held when they acted', function (): void {
    config()->set('filament-activitylog.logging.causer_role', FixedCauserRole::class);
    FixedCauserRole::$role = 'Super admin';

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');
    $user->update(['name' => 'Amaru Yupanqui']);

    $entry = lastActivity();

    FixedCauserRole::$role = 'Nobody at all';

    expect(Activity::query()->whereKey($entry->getKey())->firstOrFail()->getProperty('actors.causer_role'))
        ->toBe('Super admin');
});

test('an author whose role cannot be told is sealed by name alone', function (): void {
    config()->set('filament-activitylog.logging.causer_role', FixedCauserRole::class);
    FixedCauserRole::$role = null;

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    FixedCauserRole::$role = 'Super admin';

    expect(lastActivity()->getProperty('actors'))->toBe([
        'subject' => 'Amaru Yupanqui',
        'causer' => 'Nayra Condori',
    ]);
});

test('a configured class that answers no roles seals none', function (): void {
    config()->set('filament-activitylog.logging.causer_role', stdClass::class);

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    expect(lastActivity()->getProperty('actors'))->toBe([
        'subject' => 'Amaru Yupanqui',
        'causer' => 'Nayra Condori',
    ]);
});

test('an application that names no resolver seals no roles', function (): void {
    config()->set('filament-activitylog.logging.causer_role');

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    expect(lastActivity()->getProperty('actors'))->toBe([
        'subject' => 'Amaru Yupanqui',
        'causer' => 'Nayra Condori',
    ]);
});

test('an entry nobody caused asks no resolver for a role', function (): void {
    config()->set('filament-activitylog.logging.causer_role', FixedCauserRole::class);
    FixedCauserRole::$role = 'Super admin';

    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    expect(lastActivity()->getProperty('actors'))->toBe(['subject' => 'Amaru Yupanqui']);
});
