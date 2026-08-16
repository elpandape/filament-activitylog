<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Creates somebody, signs them in, and hands the model back.
 *
 * It returns the user rather than something chainable because almost every test that signs
 * somebody in then acts as them on a record.
 */
function signIn(string $name = 'Nayra Condori'): User
{
    $user = User::forceCreate([
        'name' => $name,
        'email' => Str::random(12).'@example.test',
        'password' => 'irrelevant',
    ]);

    Auth::login($user);

    return $user;
}

/**
 * Creates somebody without signing them in, for the record an entry is about.
 */
function makeUser(string $name = 'Amaru Quispe'): User
{
    return User::forceCreate([
        'name' => $name,
        'email' => Str::random(12).'@example.test',
        'password' => 'irrelevant',
    ]);
}

/**
 * The last entry written, which is what a test asserts on after provoking one.
 */
function lastActivity(): Activity
{
    /** @var Activity $activity */
    $activity = Activity::query()->latest('id')->firstOrFail();

    return $activity;
}
