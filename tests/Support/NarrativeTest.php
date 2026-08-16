<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Support\Narrative;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\Article;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Roles\FixedCauserRole;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Illuminate\Support\Facades\DB;

pest()->extend(TestCase::class);

test('an entry with no author reads in the passive, and names the fields only when they are few', function (): void {
    $user = makeUser('Amaru Quispe');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Amaru Quispe</strong> was created');

    $user->update(['name' => 'Amaru Yupanqui']);

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Amaru Yupanqui</strong> was updated (name)');
});

test('an event that is not one of the four model events tells what the record did', function (): void {
    $user = makeUser('Amaru Quispe');

    activity()->performedOn($user)->event('signed in')->log('Signed in');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Amaru Quispe</strong> signed in')
        ->not->toContain('was');
});

test('an author is named before the record they acted on', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Condori</strong> changed the name of <strong>Amaru Yupanqui</strong>');

    $user->delete();

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Condori</strong> deleted <strong>Amaru Yupanqui</strong>');
});

test('an author who touches more than two fields has none of them named', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui', 'email' => 'amaru@example.test']);

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Condori</strong> changed the name and email of <strong>Amaru Yupanqui</strong>');

    $user->update(['name' => 'Amaru Mamani', 'email' => 'mamani@example.test', 'password' => 'another-one']);

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Condori</strong> updated <strong>Amaru Mamani</strong>')
        ->not->toContain('email');
});

test('acting on your own record says so instead of naming you twice', function (): void {
    $actor = signIn('Nayra Condori');

    $actor->update(['name' => 'Nayra Mamani']);

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Mamani</strong> changed their own name');
});

test('acting on your own record without naming a field falls back to the record itself', function (): void {
    $actor = signIn('Nayra Condori');

    $actor->update(['name' => 'Nayra Mamani', 'email' => 'nayra@example.test', 'password' => 'another-one']);

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Mamani</strong> updated their own record');

    $actor->delete();

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Mamani</strong> deleted their own record');
});

test('an author acting on themselves with an event of their own is given no object', function (): void {
    $actor = signIn('Nayra Condori');

    activity()->performedOn($actor)->causedBy($actor)->event('signed in')->log('Signed in');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Condori</strong> signed in')
        ->not->toContain('their own');
});

test('an author acting on another record with an event of their own keeps the object', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    activity()->performedOn($user)->event('revoked')->log('Revoked');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Condori</strong> revoked <strong>Amaru Quispe</strong>');
});

test('the names sealed into the entry outlive a later rename', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    User::query()->update(['name' => 'Renamed Later']);

    $record = lastActivity()->load(['subject', 'causer']);

    expect(User::query()->where('name', 'Renamed Later')->count())->toBe(2)
        ->and(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Nayra Condori</strong> changed the name of <strong>Amaru Yupanqui</strong>')
        ->not->toContain('Renamed Later');

    $record->setAttribute('properties', collect());

    expect(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Renamed Later</strong> changed the name of <strong>Renamed Later</strong>');
});

test('a name falls back to the relation when the entry was never sealed', function (): void {
    config()->set('filament-activitylog.logging.seal_actors', false);

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $user->update(['name' => 'Amaru Yupanqui']);

    $record = lastActivity()->load(['subject', 'causer']);

    expect($record->getProperty('actors'))->toBeNull()
        ->and(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Nayra Condori</strong> changed the name of <strong>Amaru Yupanqui</strong>');
});

test('a record with nothing to be named by falls back to its class and key', function (): void {
    config()->set('filament-activitylog.logging.seal_actors', false);

    $article = Article::forceCreate(['title' => 'Andean roads']);

    activity()->performedOn($article)->event('published')->log('Published');

    $record = lastActivity()->load(['subject', 'causer']);

    expect($record->subject_id)->toBe($article->getKey())
        ->and(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Article #'.$record->subject_id.'</strong> published');

    config()->set('filament-activitylog.records.'.Article::class.'.name', 'title');

    expect(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Andean roads</strong> published');
});

test('a record whose key is not a scalar is named by its class and a question mark', function (): void {
    config()->set('filament-activitylog.logging.seal_actors', false);

    $article = Article::forceCreate(['title' => 'Andean roads']);

    activity()->performedOn($article)->event('published')->log('Published');

    $keyless = new Article;
    $record = lastActivity()->load(['subject', 'causer']);
    $record->setRelation('subject', $keyless);

    expect($keyless->getKey())->toBeNull()
        ->and(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Article #?</strong> published');
});

test('a record that no longer exists and was never sealed is named by its class and key', function (): void {
    config()->set('filament-activitylog.logging.seal_actors', false);

    $user = makeUser('Amaru Quispe');

    $user->delete();

    $record = lastActivity()->load(['subject', 'causer']);

    expect($record->getProperty('actors'))->toBeNull()
        ->and($record->getRelation('subject'))->toBeNull()
        ->and($record->subject_id)->toBe($user->getKey())
        ->and(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>User #'.$record->subject_id.'</strong> was deleted');
});

test('an entry about no record at all keeps to its description', function (): void {
    activity()->event('updated')->log('The nightly job ran');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('The nightly job ran');
});

test('an entry with no event is told with its description, and named by its author when it has one', function (): void {
    $user = makeUser('Amaru Quispe');

    activity()->performedOn($user)->log('Imported from the old system');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('Imported from the old system');

    signIn('Nayra Condori');

    activity()->performedOn($user)->log('Imported from the old system');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Condori</strong> — Imported from the old system');
});

test('a name that carries markup is escaped before it reaches the page', function (): void {
    makeUser('<b>Amaru</b> & Co');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>&lt;b&gt;Amaru&lt;/b&gt; &amp; Co</strong> was created')
        ->not->toContain('<b>');
});

test('a description that carries markup is escaped before it reaches the page', function (): void {
    signIn('Nayra Condori');

    activity()->log('<script>alert("boom")</script>');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toContain('&lt;script&gt;')
        ->toContain('<strong>Nayra Condori</strong>')
        ->not->toContain('<script>');
});

test('the role the author acted with is read from the seal and never inferred', function (): void {
    config()->set('filament-activitylog.logging.causer_role', FixedCauserRole::class);

    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    expect(Narrative::causerRole(lastActivity()))->toBe('Super admin');

    config()->set('filament-activitylog.logging.causer_role');

    $user->update(['name' => 'Amaru Yupanqui']);

    expect(Narrative::causerRole(lastActivity()))->toBeNull();
});

test('every changed field is named, and only what is safe to show is valued', function (): void {
    $user = makeUser('Amaru Quispe');

    activity()
        ->performedOn($user)
        ->event('updated')
        ->withChanges([
            'attributes' => [
                'name' => 'Amaru Yupanqui',
                'active' => true,
                'retired' => false,
                'nickname' => '',
                'tags' => ['andes', 'roads'],
                'note' => null,
                'password' => 'never-store-me',
            ],
            'old' => [
                'name' => 'Amaru Quispe',
                'email' => 'gone@example.test',
            ],
        ])
        ->log('Updated');

    expect(Narrative::amendments(lastActivity()))->toBe([
        ['field' => 'Name', 'old' => 'Amaru Quispe', 'new' => 'Amaru Yupanqui', 'masked' => false],
        ['field' => 'Active', 'old' => null, 'new' => 'true', 'masked' => false],
        ['field' => 'Retired', 'old' => null, 'new' => 'false', 'masked' => false],
        ['field' => 'Nickname', 'old' => null, 'new' => null, 'masked' => false],
        ['field' => 'Tags', 'old' => null, 'new' => '["andes","roads"]', 'masked' => false],
        ['field' => 'Note', 'old' => null, 'new' => null, 'masked' => false],
        ['field' => 'Password', 'old' => null, 'new' => null, 'masked' => true],
        ['field' => 'Email', 'old' => 'gone@example.test', 'new' => null, 'masked' => false],
    ]);
});

test('an entry that records no change amends nothing', function (): void {
    activity()->log('The nightly job ran');

    expect(Narrative::amendments(lastActivity()))->toBeEmpty();
});

test('which attributes are masked is a configuration decision, not a fixed list', function (): void {
    config()->set('filament-activitylog.masked', ['nickname']);

    $user = makeUser('Amaru Quispe');

    activity()
        ->performedOn($user)
        ->event('updated')
        ->withChanges([
            'attributes' => [
                'nickname' => 'Amaru',
                'password' => 'kept-in-the-open',
            ],
        ])
        ->log('Updated');

    expect(Narrative::isMasked('nickname'))->toBeTrue()
        ->and(Narrative::isMasked('password'))->toBeFalse()
        ->and(Narrative::amendments(lastActivity()))->toBe([
            ['field' => 'Nickname', 'old' => null, 'new' => null, 'masked' => true],
            ['field' => 'Password', 'old' => null, 'new' => 'kept-in-the-open', 'masked' => false],
        ]);
});

test('a stored value that cannot be written back as json is named as changed and left unvalued', function (): void {
    DB::table('activity_log')->insert([
        'log_name' => 'default',
        'description' => 'Imported',
        'event' => 'updated',
        'attribute_changes' => '{"attributes":{"name":"Amaru Quispe","readings":[1e999]}}',
    ]);

    $record = lastActivity();

    expect(json_encode(data_get($record->attribute_changes, 'attributes.readings')))->toBeFalse()
        ->and(Narrative::amendments($record))->toBe([
            ['field' => 'Name', 'old' => null, 'new' => 'Amaru Quispe', 'masked' => false],
            ['field' => 'Readings', 'old' => null, 'new' => null, 'masked' => false],
        ]);
});

test('an entry whose changed attributes were not stored as a list of fields is still told as a sentence', function (): void {
    $user = makeUser('Amaru Quispe');

    activity()
        ->on($user)
        ->event('updated')
        ->withChanges(['attributes' => 'oops'])
        ->log('Imported from the old system');

    $record = lastActivity()->load(['subject', 'causer']);

    expect(data_get($record->attribute_changes, 'attributes'))->toBe('oops')
        ->and(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Amaru Quispe</strong> was updated')
        ->and(Narrative::amendments($record))->toBeEmpty();
});

test('an author is still named over an entry whose changed attributes were not stored as a list', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    activity()
        ->on($user)
        ->event('updated')
        ->withChanges(['attributes' => 'oops', 'old' => 'nope'])
        ->log('Imported from the old system');

    $record = lastActivity()->load(['subject', 'causer']);

    expect(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Nayra Condori</strong> updated <strong>Amaru Quispe</strong>')
        ->and(Narrative::amendments($record))->toBeEmpty();
});

test('acting on your own record over changes that were not stored as a list falls back to the record itself', function (): void {
    $actor = signIn('Nayra Condori');

    activity()
        ->on($actor)
        ->event('updated')
        ->withChanges(['attributes' => 'oops'])
        ->log('Imported from the old system');

    expect(Narrative::sentence(lastActivity()->load(['subject', 'causer']))->toHtml())
        ->toBe('<strong>Nayra Condori</strong> updated their own record');
});

test('an entry whose previous values were not stored as a list still names the fields that changed', function (): void {
    $user = makeUser('Amaru Quispe');

    activity()
        ->on($user)
        ->event('updated')
        ->withChanges(['attributes' => ['name' => 'Amaru Yupanqui'], 'old' => 'nope'])
        ->log('Imported from the old system');

    $record = lastActivity()->load(['subject', 'causer']);

    expect(data_get($record->attribute_changes, 'old'))->toBe('nope')
        ->and(Narrative::sentence($record)->toHtml())
        ->toBe('<strong>Amaru Quispe</strong> was updated (name)')
        ->and(Narrative::amendments($record))->toBe([
            ['field' => 'Name', 'old' => null, 'new' => 'Amaru Yupanqui', 'masked' => false],
        ]);
});

test('initials take the first letter of the first two words, whatever alphabet they are in', function (): void {
    expect(Narrative::initials('ñusta ámaru quispe'))->toBe('ÑÁ')
        ->and(Narrative::initials('Amaru'))->toBe('A')
        ->and(Narrative::initials('   Nayra   Condori   '))->toBe('NC')
        ->and(Narrative::initials('   '))->toBeEmpty();
});
