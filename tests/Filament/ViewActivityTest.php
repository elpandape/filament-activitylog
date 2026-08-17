<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Pages\ViewActivity;
use ElPandaPe\FilamentActivitylog\Support\Narrative;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\UserResource;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\Article;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Policies\ActivityPolicy;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Policies\UserPolicy;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Roles\FixedCauserRole;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

pest()->extend(TestCase::class);

$originCard = 'border-left: 2px solid var(--info-500)';
$addressLabel = '>IP: </span>';
$agentLine = 'display: block; font-weight: 600; overflow-wrap: anywhere;">';
$logBadge = '>Log</span>';

test('the detail page mounts for an entry and names it by its key', function (): void {
    signIn();
    $user = makeUser();
    $user->update(['name' => 'Amaru Yupanqui']);

    $entry = lastActivity();

    livewire(ViewActivity::class, ['record' => $entry->id])
        ->assertSee('Entry #'.$entry->id)
        ->assertOk();
});

test('the detail shows the description as stored and not the sentence the listing narrates', function (): void {
    $causer = signIn('Nayra Condori');

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser('Amaru Quispe'))
        ->event('updated')
        ->withChanges(['old' => ['name' => 'Amaru Quispe'], 'attributes' => ['name' => 'Amaru Yupanqui']])
        ->log('Account amended by hand');

    $entry = lastActivity();

    expect((string) Narrative::sentence($entry))->toContain('changed the name of');

    livewire(ViewActivity::class, ['record' => $entry->id])
        ->assertSee('Account amended by hand')
        ->assertDontSee('changed the name of');
});

test('a translated description is said in the panel language and keeps the stored words in its title', function (): void {
    config()->set('filament-activitylog.descriptions', ['Cuenta actualizada' => 'Account updated']);

    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->log('Cuenta actualizada');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSee('Account updated')
        ->assertSeeHtml('title="Cuenta actualizada"');
});

test('the author is rendered before the affected record', function (): void {
    $causer = signIn('Nayra Condori');

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser('Amaru Quispe'))
        ->event('updated')
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeInOrder(['Author', 'Nayra Condori', 'Affected record', 'Amaru Quispe'])
        ->assertDontSee(['System', 'Nobody was signed in']);
});

test('the author is placed by the role sealed onto the entry and not by their record type', function (): void {
    config()->set('filament-activitylog.records.'.Article::class.'.name', 'title');
    config()->set('filament-activitylog.logging.causer_role', FixedCauserRole::class);

    FixedCauserRole::$role = 'Super admin';

    $causer = signIn('Nayra Condori');

    activity()
        ->causedBy($causer)
        ->performedOn(Article::forceCreate(['title' => 'The Ruins of Wari']))
        ->event('updated')
        ->log('Article amended by hand');

    expect(lastActivity()->getProperty('actors.causer_role'))->toBe('Super admin');

    /** @var int $causerKey */
    $causerKey = $causer->getKey();

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeInOrder([
            'Author',
            'Nayra Condori',
            'Super admin',
            '#'.$causerKey,
            'Affected record',
            'The Ruins of Wari',
            'Article',
        ])
        ->assertDontSee('User');
});

test('the author keeps the role they acted with after it is taken away from them', function (): void {
    config()->set('filament-activitylog.logging.causer_role', FixedCauserRole::class);
    FixedCauserRole::$role = 'Field auditor';

    $causer = signIn('Nayra Condori');

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser('Amaru Quispe'))
        ->event('updated')
        ->log('Account amended by hand');

    FixedCauserRole::$role = 'Super admin';

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeInOrder(['Nayra Condori', 'Field auditor', 'Affected record'])
        ->assertDontSee('Super admin');
});

test('a masked attribute is named but its value is never shown', function (): void {
    config()->set('filament-activitylog.logging.mask_secrets', false);

    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->withChanges([
            'old' => ['name' => 'Amaru Quispe', 'password' => 'hash-of-the-old-password'],
            'attributes' => ['name' => 'Amaru Yupanqui', 'password' => 'hash-of-the-new-password'],
        ])
        ->log('Password changed');

    $entry = lastActivity();

    expect(data_get($entry->attribute_changes, 'attributes.password'))->toBe('hash-of-the-new-password')
        ->and(data_get($entry->attribute_changes, 'old.password'))->toBe('hash-of-the-old-password');

    livewire(ViewActivity::class, ['record' => $entry->id])
        ->assertSeeInOrder([
            'Attribute',
            'Before',
            'After',
            'Password',
            'hidden · the value is not shown before or after',
        ])
        ->assertSeeHtml('>Password</span>')
        ->assertSee('Amaru Yupanqui')
        ->assertDontSee('hash-of-the-new-password')
        ->assertDontSee('hash-of-the-old-password');
});

test('a change shows the value the attribute had before and the one it took after', function (): void {
    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->withChanges([
            'old' => ['email' => 'quispe@example.test'],
            'attributes' => ['email' => 'yupanqui@example.test'],
        ])
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeInOrder([
            'Attribute',
            'Before',
            'After',
            'Email',
            'quispe@example.test',
            'yupanqui@example.test',
        ])
        ->assertDontSee(['no value', 'first value', 'cleared']);
});

test('an attribute that had nothing before it shows the gap and is called a first value', function (): void {
    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->withChanges([
            'old' => ['nickname' => null],
            'attributes' => ['nickname' => 'Chaska'],
        ])
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeInOrder([
            'Before',
            'After',
            'Nickname',
            'no value',
            'Chaska',
            'first value',
        ])
        ->assertDontSee('cleared');
});

test('an attribute left with nothing after it shows the gap and is called cleared', function (): void {
    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->withChanges([
            'old' => ['nickname' => 'Chaska'],
            'attributes' => ['nickname' => null],
        ])
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeInOrder([
            'Before',
            'After',
            'Nickname',
            'Chaska',
            'no value',
            'cleared',
        ])
        ->assertDontSee('first value');
});

test('an attribute empty on both sides shows the gap twice and claims nothing about it', function (): void {
    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->withChanges([
            'old' => ['nickname' => ''],
            'attributes' => ['nickname' => ''],
        ])
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeInOrder(['Nickname', 'no value', 'no value'])
        ->assertDontSee(['first value', 'cleared']);
});

test('an entry that changed no attribute says so instead of drawing an empty table', function (): void {
    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('signed in')
        ->log('Session opened');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSee('Session opened')
        ->assertSee('No attributes changed')
        ->assertDontSee('Attribute');
});

test('an entry nobody caused is attributed to the system', function (): void {
    signIn();

    activity()
        ->causedByAnonymous()
        ->performedOn(makeUser('Amaru Quispe'))
        ->event('created')
        ->log('Account created');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSee('Amaru Quispe')
        ->assertSee('System')
        ->assertSee('Nobody was signed in: a command, a job or a deployment operation.');
});

test('an entry about a deleted record still names it from the seal', function (): void {
    $causer = signIn();
    $user = makeUser('Amaru Quispe');

    $author = UserResource::getUrl('view', ['record' => $causer], isAbsolute: false);
    $gone = UserResource::getUrl('view', ['record' => $user], isAbsolute: false);

    $user->delete();

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSee('Amaru Quispe')
        ->assertSeeHtml('href="'.$author.'"')
        ->assertDontSeeHtml('href="'.$gone.'"');
});

test('a party links to its record when the panel shows it and the viewer may see it', function (): void {
    $causer = signIn();
    $user = makeUser('Amaru Quispe');

    activity()
        ->causedBy($causer)
        ->performedOn($user)
        ->event('updated')
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeHtml('href="'.UserResource::getUrl('view', ['record' => $user], isAbsolute: false).'"')
        ->assertSeeHtml('>Amaru Quispe</a>');
});

test('a party no resource shows is named without a link', function (): void {
    config()->set('filament-activitylog.records.'.Article::class.'.name', 'title');

    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(Article::forceCreate(['title' => 'The Ruins of Wari']))
        ->event('updated')
        ->log('Article amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSee('The Ruins of Wari')
        ->assertSeeHtml('href="'.UserResource::getUrl('view', ['record' => $causer], isAbsolute: false).'"')
        ->assertDontSeeHtml('>The Ruins of Wari</a>');
});

test('a party the viewer may not see is named without a link', function (): void {
    $causer = signIn();
    $user = makeUser('Amaru Quispe');

    activity()
        ->causedBy($causer)
        ->performedOn($user)
        ->event('updated')
        ->log('Account amended by hand');

    $url = UserResource::getUrl('view', ['record' => $user], isAbsolute: false);

    UserPolicy::$view = false;

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSee('Amaru Quispe')
        ->assertDontSeeHtml('href="'.$url.'"')
        ->assertDontSeeHtml('>Amaru Quispe</a>');
});

test('the context tells where the entry came from and where it came in through', function () use ($originCard, $addressLabel, $agentLine, $logBadge): void {
    $causer = signIn();

    activity()
        ->inLog('security')
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->withProperties([
            'ip' => '203.0.113.9',
            'agent' => 'TestBrowser 1.0',
            'via' => 'PATCH /test/users/7',
        ])
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeInOrder(['Origin', 'TestBrowser 1.0', '203.0.113.9', 'Entry point', 'security'])
        ->assertSeeHtml($originCard)
        ->assertSeeHtml($agentLine.'TestBrowser 1.0</span>')
        ->assertSeeHtml($addressLabel)
        ->assertSeeHtml($logBadge)
        ->assertSeeText('PATCH /test/users/7');
});

test('an entry that names its agent and no address shows the origin without an address', function () use ($originCard, $addressLabel, $agentLine): void {
    $causer = signIn();
    askingFromNobody();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->withProperties(['agent' => 'TestBrowser 1.0'])
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeHtml($originCard)
        ->assertSeeHtml($agentLine.'TestBrowser 1.0</span>')
        ->assertDontSeeHtml($addressLabel)
        ->assertDontSee('Outside a web request');
});

test('an entry that names its address and no agent shows the origin without an agent', function () use ($originCard, $addressLabel, $agentLine): void {
    $causer = signIn();
    askingFromNobody();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->withProperties(['ip' => '198.51.100.4'])
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSeeHtml($originCard)
        ->assertSeeHtml($addressLabel)
        ->assertSee('198.51.100.4')
        ->assertDontSeeHtml($agentLine)
        ->assertDontSee('Outside a web request');
});

test('an entry made outside a request reports the absence instead of leaving blanks', function () use ($originCard, $addressLabel, $agentLine): void {
    $causer = signIn();
    askingFromNobody();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->log('Account amended by hand');

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertSee('Origin')
        ->assertSee('Outside a web request: a command, a job or a deployment operation.')
        ->assertDontSeeHtml($originCard)
        ->assertDontSeeHtml($addressLabel)
        ->assertDontSeeHtml($agentLine);
});

test('an entry belonging to no log leaves out the badge and still tells the path', function () use ($logBadge): void {
    signIn();

    $entry = Activity::query()->create([
        'log_name' => null,
        'description' => 'Account amended by hand',
        'event' => 'updated',
        'properties' => ['via' => 'PATCH /test/users/7'],
    ]);

    livewire(ViewActivity::class, ['record' => $entry->getKey()])
        ->assertSee('Entry point')
        ->assertSeeText('PATCH /test/users/7')
        ->assertDontSeeHtml($logBadge);
});

test('the page is forbidden to somebody who may not view the entry', function (): void {
    $causer = signIn();

    activity()
        ->causedBy($causer)
        ->performedOn(makeUser())
        ->event('updated')
        ->log('Account amended by hand');

    ActivityPolicy::$view = false;

    livewire(ViewActivity::class, ['record' => lastActivity()->id])
        ->assertForbidden();
});
