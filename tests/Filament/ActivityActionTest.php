<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\ActivityResource;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\Pages\ListUsers;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\Pages\ViewUser;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\CustomActivity;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Policies\ActivityPolicy;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Providers\BarePanelProvider;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

use function Pest\Laravel\travelTo;
use function Pest\Livewire\livewire;

pest()->extend(TestCase::class);

test('the drawer opens from a table row and from a page header, headed by the record and closeable', function (): void {
    signIn();
    $user = makeUser('Amaru Quispe');
    $user->update(['name' => 'Amaru Yupanqui']);

    livewire(ListUsers::class)
        ->mountAction(TestAction::make('activity')->table($user))
        ->assertMountedActionModalSee(['Activity of Amaru Yupanqui', 'Close', 'changed the name of']);

    livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity')
        ->assertMountedActionModalSee(['Activity of Amaru Yupanqui', 'Close', 'changed the name of']);
});

test('a drawer that only reads offers no way to save', function (): void {
    signIn();
    $user = makeUser();

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('fi-modal-footer-actions');
    $drawer->assertMountedActionModalSee('Close');
    $drawer->assertMountedActionModalDontSeeHtml('type="submit"');
    $drawer->assertMountedActionModalDontSee('Submit');

    /** @var ViewUser $page */
    $page = $drawer->instance();

    expect($page->getMountedAction()?->getModalSubmitAction())->toBeNull();
});

test('the drawer tells the history of the record it hangs on and of no other', function (): void {
    signIn();
    $amaru = makeUser('Amaru Quispe');
    $wayra = makeUser('Wayra Mamani');

    activity()->performedOn($amaru)->event('opened the vault')->log('a');
    activity()->performedOn($wayra)->event('closed the gate')->log('b');

    $drawer = livewire(ListUsers::class)
        ->mountAction(TestAction::make('activity')->table($amaru));

    $drawer->assertMountedActionModalSee(['opened the vault', 'Amaru Quispe']);
    $drawer->assertMountedActionModalDontSee(['closed the gate', 'Wayra Mamani']);
});

test('past the configured limit the drawer keeps the newest entries and says how many there are', function (): void {
    config()->set('filament-activitylog.timeline.limit', 2);

    signIn();
    $user = makeUser();

    Activity::query()->delete();

    activity()->performedOn($user)->event('first step')->log('a');
    activity()->performedOn($user)->event('second step')->log('b');
    activity()->performedOn($user)->event('third step')->log('c');

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSee(['third step', 'second step', 'Showing the last 2 of 3 entries.']);
    $drawer->assertMountedActionModalDontSee('first step');
});

test('within the limit the drawer says nothing about there being more', function (): void {
    config()->set('filament-activitylog.timeline.limit', 25);

    signIn();
    $user = makeUser();

    Activity::query()->delete();

    activity()->performedOn($user)->event('first step')->log('a');
    activity()->performedOn($user)->event('second step')->log('b');

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSee(['first step', 'second step']);
    $drawer->assertMountedActionModalDontSee('Showing the last');
});

test('the classic reading draws the history as one entry per row', function (): void {
    config()->set('filament-activitylog.timeline.style', 'classic');

    signIn();
    $user = makeUser();

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('data-timeline="classic"');
    $drawer->assertMountedActionModalDontSeeHtml('data-timeline="thread"');
});

test('the thread reading draws the history as one continuous line', function (): void {
    config()->set('filament-activitylog.timeline.style', 'thread');

    signIn();
    $user = makeUser();

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('data-timeline="thread"');
    $drawer->assertMountedActionModalDontSeeHtml('data-timeline="classic"');
});

test('an unknown reading falls back to the classic one instead of refusing to open', function (): void {
    config()->set('filament-activitylog.timeline.style', 'engraved');

    signIn();
    $user = makeUser();

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('data-timeline="classic"');
    $drawer->assertMountedActionModalDontSeeHtml('data-timeline="thread"');
});

test('the thread reading rings an attribute change and medallions what opens a life', function (): void {
    config()->set('filament-activitylog.timeline.style', 'thread');

    signIn();
    $user = makeUser('Amaru Quispe');
    $user->update(['name' => 'Amaru Yupanqui']);

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('width: 0.6875rem; height: 0.6875rem; border-radius: 9999px; border: 1.5px solid color-mix(in oklab, var(--gray-500) 26%, transparent);');
    $drawer->assertMountedActionModalSeeHtml('width: 1.875rem; height: 1.875rem; border-radius: 9999px; border: 1px solid color-mix(in oklab, var(--success-500) 45%, transparent); background-color: color-mix(in oklab, var(--success-500) 12%, transparent); color: var(--success-600);');
    $drawer->assertMountedActionModalDontSeeHtml('var(--info-500)');
});

test('the thread reading heads today and yesterday by name and older days by their date', function (): void {
    travelTo('2026-08-16 15:00:00');

    config()->set('filament-activitylog.timeline.style', 'thread');

    signIn();
    $user = makeUser();

    Activity::query()->delete();

    $now = CarbonImmutable::now();

    activity()->performedOn($user)->event('newest step')->createdAt($now)->log('a');
    activity()->performedOn($user)->event('middle step')->createdAt($now->subDay())->log('b');
    activity()->performedOn($user)->event('oldest step')->createdAt($now->subDays(7))->log('c');

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSee(['newest step', 'middle step', 'oldest step']);
    $drawer->assertMountedActionModalSeeHtml('<span>Today</span>');
    $drawer->assertMountedActionModalSeeHtml('<span>Yesterday</span>');
    $drawer->assertMountedActionModalSeeHtml('<span>9 Aug 2026</span>');
    $drawer->assertMountedActionModalDontSeeHtml('<span>16 Aug 2026</span>');
});

test('past the configured limit the thread reading also says how many there are', function (): void {
    config()->set('filament-activitylog.timeline.style', 'thread');
    config()->set('filament-activitylog.timeline.limit', 2);

    signIn();
    $user = makeUser();

    Activity::query()->delete();

    activity()->performedOn($user)->event('first step')->log('a');
    activity()->performedOn($user)->event('second step')->log('b');
    activity()->performedOn($user)->event('third step')->log('c');

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('data-timeline="thread"');
    $drawer->assertMountedActionModalSee(['third step', 'second step', 'Showing the last 2 of 3 entries.']);
    $drawer->assertMountedActionModalDontSee('first step');
});

test('the thread reading also says when nothing has been recorded about a record', function (): void {
    config()->set('filament-activitylog.timeline.style', 'thread');

    signIn();
    $user = makeUser();

    Activity::query()->delete();

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSee('Nothing has been recorded about this record yet.');
    $drawer->assertMountedActionModalDontSeeHtml('data-timeline=');
});

test('somebody who may read the log is offered the drawer on the row and in the header', function (): void {
    signIn();
    $user = makeUser();

    livewire(ListUsers::class)
        ->assertActionVisible(TestAction::make('activity')->table($user));

    livewire(ViewUser::class, ['record' => $user->getKey()])
        ->assertActionVisible('activity');
});

test('somebody who may see users but not the log is not offered the drawer at all', function (): void {
    signIn();
    $user = makeUser();

    ActivityPolicy::$viewAny = false;

    livewire(ListUsers::class)
        ->assertActionVisible(TestAction::make('view')->table($user))
        ->assertActionHidden(TestAction::make('activity')->table($user));

    livewire(ViewUser::class, ['record' => $user->getKey()])
        ->assertActionHidden('activity');
});

test('a record nothing has been recorded about says so instead of drawing an empty timeline', function (): void {
    signIn();
    $user = makeUser();

    Activity::query()->delete();

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSee('Nothing has been recorded about this record yet.');
    $drawer->assertMountedActionModalDontSeeHtml('data-timeline=');
});

test('the drawer lists the newest entry first, by date rather than by id', function (): void {
    signIn();
    $user = makeUser();

    Activity::query()->delete();

    $moment = CarbonImmutable::now()->subHours(2);

    activity()->performedOn($user)->event('alpha step')->createdAt($moment)->log('a');
    activity()->performedOn($user)->event('beta step')->createdAt($moment)->log('b');
    activity()->performedOn($user)->event('gamma step')->createdAt($moment->subDay())->log('c');

    $html = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity')
        ->getMountedActionModalHtml();

    preg_match_all('/(?:alpha|beta|gamma) step/', $html, $matches);

    expect($matches[0])->toBe(['beta step', 'alpha step', 'gamma step']);
});

test('every entry is told as a sentence naming the author and the record', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity')
        ->assertMountedActionModalSeeHtml('<strong>Nayra Condori</strong> created <strong>Amaru Quispe</strong>');
});

test('an author the row never sealed is named all the same, from the relation loaded ahead of the entries', function (): void {
    $author = signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    $sparse = User::query()->select('id')->whereKey($author->getKey())->firstOrFail();

    activity()->causedBy($sparse)->performedOn($user)->event('updated')->log('Account updated');

    expect(data_get(lastActivity()->properties, 'actors'))->toBe(['subject' => 'Amaru Quispe']);

    livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity')
        ->assertMountedActionModalSeeHtml('<strong>Nayra Condori</strong> updated <strong>Amaru Quispe</strong>');
});

test('the classic reading names both parties of entries the application never sealed', function (): void {
    config()->set('filament-activitylog.timeline.style', 'classic');
    config()->set('filament-activitylog.logging.seal_actors', false);

    $author = signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    activity()->causedBy($author)->performedOn($user)->event('opened the vault')->log('a');

    expect(data_get(lastActivity()->properties, 'actors'))->toBeNull()
        ->and(Activity::query()->whereMorphedTo('subject', $user)->count())->toBeGreaterThan(1);

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('<strong>Nayra Condori</strong> opened the vault <strong>Amaru Quispe</strong>');
    $drawer->assertMountedActionModalSeeHtml('<strong>Nayra Condori</strong> created <strong>Amaru Quispe</strong>');
});

test('the thread reading names both parties of entries the application never sealed', function (): void {
    config()->set('filament-activitylog.timeline.style', 'thread');
    config()->set('filament-activitylog.logging.seal_actors', false);

    $author = signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    activity()->causedBy($author)->performedOn($user)->event('opened the vault')->log('a');

    expect(data_get(lastActivity()->properties, 'actors'))->toBeNull()
        ->and(Activity::query()->whereMorphedTo('subject', $user)->count())->toBeGreaterThan(1);

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('data-timeline="thread"');
    $drawer->assertMountedActionModalSeeHtml('<strong>Nayra Condori</strong> opened the vault <strong>Amaru Quispe</strong>');
    $drawer->assertMountedActionModalSeeHtml('<strong>Nayra Condori</strong> created <strong>Amaru Quispe</strong>');
});

test('hung where no record is in scope the drawer opens on its empty state', function (): void {
    signIn();
    makeUser('Amaru Quispe');

    expect(Activity::query()->count())->toBeGreaterThan(0);

    $drawer = livewire(ListUsers::class)
        ->mountAction('activity');

    $drawer->assertMountedActionModalSee('Nothing has been recorded about this record yet.');
    $drawer->assertMountedActionModalDontSee('Amaru Quispe');
    $drawer->assertMountedActionModalDontSeeHtml('data-timeline=');

    /** @var ListUsers $page */
    $page = $drawer->instance();

    expect($page->getMountedAction()?->getRecord())->toBeNull();
});

test('the drawer is offered when the policy of the configured activity model allows it', function (): void {
    signIn('Nayra Condori');
    $user = makeUser('Amaru Quispe');

    config()->set('activitylog.activity_model', CustomActivity::class);

    ActivityPolicy::$viewAny = false;

    $policy = new class
    {
        public function viewAny(User $user): bool
        {
            return true;
        }

        public function view(User $user, CustomActivity $activity): bool
        {
            return true;
        }
    };

    Gate::policy(CustomActivity::class, $policy::class);

    livewire(ListUsers::class)
        ->assertActionVisible(TestAction::make('activity')->table($user));

    livewire(ViewUser::class, ['record' => $user->getKey()])
        ->assertActionVisible('activity')
        ->mountAction('activity')
        ->assertMountedActionModalSeeHtml('<strong>Nayra Condori</strong> created <strong>Amaru Quispe</strong>');
});

test('the drawer is refused when the policy of the configured activity model refuses it', function (): void {
    signIn();
    $user = makeUser();

    config()->set('activitylog.activity_model', CustomActivity::class);

    $policy = new class
    {
        public function viewAny(User $user): bool
        {
            return false;
        }
    };

    Gate::policy(CustomActivity::class, $policy::class);

    expect(ActivityPolicy::$viewAny)->toBeTrue();

    livewire(ListUsers::class)
        ->assertActionHidden(TestAction::make('activity')->table($user));

    livewire(ViewUser::class, ['record' => $user->getKey()])
        ->assertActionHidden('activity');
});

test('an entry links to its own page when the log is on the panel and the reader may open it', function (): void {
    signIn();
    $user = makeUser();

    $entry = lastActivity();

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('<a href="'.ActivityResource::getUrl('view', ['record' => $entry], isAbsolute: false).'"');
    $drawer->assertMountedActionModalSeeHtml('>#'.$entry->id.'</a>');
});

test('an entry the reader may not open shows its number without a link', function (): void {
    signIn();
    $user = makeUser();

    $entry = lastActivity();

    ActivityPolicy::$view = false;

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('>#'.$entry->id.'</span>');
    $drawer->assertMountedActionModalDontSeeHtml('>#'.$entry->id.'</a>');
});

test('on a panel that never installed the plugin an entry shows its number and leads nowhere', function (): void {
    signIn();
    $user = makeUser();

    $entry = lastActivity();

    BarePanelProvider::install();

    Filament::setCurrentPanel('bare');

    expect(Filament::getCurrentPanel()?->getId())->toBe('bare')
        ->and(Filament::getModelResource($entry))->toBeNull()
        ->and(fn (): string => ActivityResource::getUrl('view', ['record' => $entry], isAbsolute: false))
        ->toThrow(RouteNotFoundException::class);

    $drawer = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $drawer->assertMountedActionModalSeeHtml('>#'.$entry->id.'</span>');
    $drawer->assertMountedActionModalDontSeeHtml('>#'.$entry->id.'</a>');

    Filament::setCurrentPanel('test');

    expect(Filament::getCurrentPanel()?->getId())->toBe('test');
});

test('the thread reading resolves the entry link by the same rule', function (): void {
    config()->set('filament-activitylog.timeline.style', 'thread');

    signIn();
    $user = makeUser();

    $entry = lastActivity();

    $linked = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $linked->assertMountedActionModalSeeHtml('data-timeline="thread"');
    $linked->assertMountedActionModalSeeHtml('>#'.$entry->id.'</a>');

    ActivityPolicy::$view = false;

    $plain = livewire(ViewUser::class, ['record' => $user->getKey()])
        ->mountAction('activity');

    $plain->assertMountedActionModalSeeHtml('>#'.$entry->id.'</span>');
    $plain->assertMountedActionModalDontSeeHtml('>#'.$entry->id.'</a>');
});
