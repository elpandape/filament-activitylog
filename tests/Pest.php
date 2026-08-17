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

/*
|--------------------------------------------------------------------------
| Where a write came from
|--------------------------------------------------------------------------
|
| The request is swapped rather than mutated: `Request::instance()` is one object for the whole
| process, so setting a header on it leaks into every test that runs after this one.
|
*/

function askingFrom(?string $address = '203.0.113.7', ?string $agent = null): void
{
    $request = Illuminate\Http\Request::create('/admin/security/activity', 'GET');

    if (is_string($address)) {
        $request->server->set('REMOTE_ADDR', $address);
    } else {
        // Symfony puts a loopback address on every request it builds, and this is how a command,
        // a job or a scheduled task looks: nobody on the other end.
        $request->server->remove('REMOTE_ADDR');
    }

    if (is_string($agent)) {
        $request->headers->set('User-Agent', $agent);
    } else {
        $request->headers->remove('User-Agent');
    }

    app()->instance('request', $request);

    // The facade caches whatever it resolved first, so rebinding the container alone leaves
    // `Request::ip()` answering from the request this one replaced.
    Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
}

function askingFromNobody(): void
{
    askingFrom(null);
}
