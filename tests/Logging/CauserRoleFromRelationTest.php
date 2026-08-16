<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Logging\CauserRoleFromRelation;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\Article;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\Role;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;

pest()->extend(TestCase::class);

function roleNamed(string $name, ?string $title = null): Role
{
    return Role::forceCreate(['name' => $name, 'title' => $title]);
}

test('a role is read by the title it goes by', function (): void {
    $causer = makeUser();
    $causer->roles()->attach(roleNamed('super-admin', 'Super admin'));

    expect((new CauserRoleFromRelation)($causer))->toBe('Super admin');
});

test('a role with no title of its own is read by its name', function (): void {
    $causer = makeUser();
    $causer->roles()->attach(roleNamed('auditor'));

    expect((new CauserRoleFromRelation)($causer))->toBe('auditor');
});

test('a role with neither a title nor a name answers nothing', function (): void {
    $causer = makeUser();
    $causer->roles()->attach(roleNamed('', ''));

    expect((new CauserRoleFromRelation)($causer))->toBeNull();
});

test('somebody holding no role at all answers nothing', function (): void {
    expect((new CauserRoleFromRelation)(makeUser()))->toBeNull();
});

test('a roles that answers something other than a relation is refused', function (): void {
    $article = Article::forceCreate(['title' => 'On auditing']);

    expect((new CauserRoleFromRelation)($article))->toBeNull();
});

test('a model that has no roles at all is never asked for one', function (): void {
    signIn('Nayra Condori');
    makeUser('Amaru Quispe');

    expect((new CauserRoleFromRelation)(lastActivity()))->toBeNull();
});

test('of several roles the oldest is the one read, and it does not change between entries', function (): void {
    $causer = makeUser();

    $primero = roleNamed('auditor', 'Auditor');
    $segundo = roleNamed('super-admin', 'Super admin');

    $causer->roles()->attach([$segundo->getKey(), $primero->getKey()]);

    expect((new CauserRoleFromRelation)($causer))->toBe('Auditor')
        ->and((new CauserRoleFromRelation)($causer))->toBe('Auditor');
});

test('an entry seals the role its author held, with nobody having configured anything', function (): void {
    $causer = signIn('Nayra Condori');
    $causer->roles()->attach(roleNamed('super-admin', 'Super admin'));

    makeUser('Amaru Quispe')->update(['name' => 'Amaru Yupanqui']);

    expect(lastActivity()->getProperty('actors.causer_role'))->toBe('Super admin');
});

test('an application that wants no role sealed says so with null', function (): void {
    config()->set('filament-activitylog.logging.causer_role');

    $causer = signIn('Nayra Condori');
    $causer->roles()->attach(roleNamed('super-admin', 'Super admin'));

    makeUser('Amaru Quispe')->update(['name' => 'Amaru Yupanqui']);

    expect(lastActivity()->getProperty('actors.causer_role'))->toBeNull();
});
