<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\ActivityResource;
use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Pages\ListActivities;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Policies\ActivityPolicy;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Filament\Navigation\NavigationItem;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\travelTo;
use function Pest\Livewire\livewire;

pest()->extend(TestCase::class);

$entry = static fn (
    string $description,
    CarbonInterface $at,
    ?string $event = 'updated',
    ?Model $subject = null,
    ?Model $causer = null,
): Activity => Activity::query()->create([
    'log_name' => 'default',
    'description' => $description,
    'event' => $event,
    'subject_type' => $subject?->getMorphClass(),
    'subject_id' => $subject?->getKey(),
    'causer_type' => $causer?->getMorphClass(),
    'causer_id' => $causer?->getKey(),
    'created_at' => $at,
    'updated_at' => $at,
]);

test('the log reads newest first, under the day each entry belongs to', function () use ($entry): void {
    travelTo('2026-08-16 15:00:00');
    signIn();
    Activity::query()->delete();

    $justNow = $entry('Account updated', now()->subMinute());
    $earlierToday = $entry('Account updated', now()->subHours(3));
    $yesterday = $entry('Account updated', now()->subDay());
    $lastWeek = $entry('Account updated', now()->subDays(7));

    $component = livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$justNow, $earlierToday, $yesterday, $lastWeek], inOrder: true)
        ->assertSee('Today · 16 Aug 2026')
        ->assertSee('Yesterday · 15 Aug 2026')
        ->assertSee('9 Aug 2026');

    /** @var ListActivities $page */
    $page = $component->instance();

    /** @var Group $group */
    $group = $page->getTable()->getDefaultGroup();

    /** @var HtmlString $lastWeekTitle */
    $lastWeekTitle = $group->getTitle($lastWeek);

    expect($lastWeekTitle->toHtml())->toBe('9 Aug 2026');
});

test('the day heading each block is html the table never parses as a date', function () use ($entry): void {
    travelTo('2026-08-16 15:00:00');
    signIn();

    $today = $entry('Account updated', now());

    $component = livewire(ListActivities::class);

    /** @var ListActivities $page */
    $page = $component->instance();

    /** @var Group $group */
    $group = $page->getTable()->getDefaultGroup();

    expect($group->getTitle($today))->toBeInstanceOf(HtmlString::class);

    /** @var HtmlString $title */
    $title = $group->getTitle($today);

    expect($title->toHtml())->toBe('Today · '.now()->translatedFormat('j M Y'));

    $component->assertSee('Today · '.now()->translatedFormat('j M Y'));
});

test('the day heading says the day and nothing else, with no column label glued in front', function () use ($entry): void {
    travelTo('2026-08-16 15:00:00');
    signIn();
    Activity::query()->delete();

    $today = $entry('Account updated', now());

    $component = livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$today])
        ->assertDontSee('Created at:');

    /** @var ListActivities $page */
    $page = $component->instance();

    /** @var Group $group */
    $group = $page->getTable()->getDefaultGroup();

    expect($group->getLabel())->toBe('Created at')
        ->and($group->isTitlePrefixedWithLabel())->toBeFalse()
        ->and($component->html())->toMatch(
            '/class="fi-ta-group-heading"[^>]*>(?:\s|<!--\[if [A-Z]+\]><!\[endif\]-->)*Today · 16 Aug 2026\s*</u',
        );
});

test('the same day of two different years heads two blocks and not one', function () use ($entry): void {
    travelTo('2026-08-16 15:00:00');
    signIn();
    Activity::query()->delete();

    $lastYear = $entry('Account updated', now()->subYear());
    $twoYearsAgo = $entry('Account updated', now()->subYears(2));

    $component = livewire(ListActivities::class);

    /** @var ListActivities $page */
    $page = $component->instance();

    /** @var Group $group */
    $group = $page->getTable()->getDefaultGroup();

    /** @var HtmlString $lastYearTitle */
    $lastYearTitle = $group->getTitle($lastYear);

    /** @var HtmlString $twoYearsAgoTitle */
    $twoYearsAgoTitle = $group->getTitle($twoYearsAgo);

    expect($lastYearTitle->toHtml())->toBe('16 Aug 2025')
        ->and($twoYearsAgoTitle->toHtml())->toBe('16 Aug 2024');

    $component
        ->assertCanSeeTableRecords([$lastYear, $twoYearsAgo], inOrder: true)
        ->assertSee('16 Aug 2025')
        ->assertSee('16 Aug 2024');
});

test('the day heading and the clock of a row are written in the formats the configuration names', function () use ($entry): void {
    travelTo('2026-08-16 15:00:00');
    signIn();
    Activity::query()->delete();

    $justNow = $entry('Account updated', now()->subMinute());
    $lastWeek = $entry('Account updated', now()->subDays(7));

    livewire(ListActivities::class)
        ->assertSee('Today · 16 Aug 2026')
        ->assertSee('9 Aug 2026')
        ->assertTableColumnFormattedStateSet('created_at', '14:59', $justNow)
        ->assertTableColumnFormattedStateSet('created_at', '15:00', $lastWeek);

    config()->set('filament-activitylog.formats.date', 'Y/m/d');
    config()->set('filament-activitylog.formats.time', 'h:i A');

    livewire(ListActivities::class)
        ->assertSee('Today · 2026/08/16')
        ->assertSee('2026/08/09')
        ->assertTableColumnFormattedStateSet('created_at', '02:59 PM', $justNow)
        ->assertTableColumnFormattedStateSet('created_at', '03:00 PM', $lastWeek)
        ->assertDontSee('16 Aug 2026')
        ->assertDontSee('9 Aug 2026')
        ->assertDontSee('14:59');
});

test('under the clock of a row is how long ago the entry was written', function () use ($entry): void {
    travelTo('2026-08-16 15:00:00');
    signIn();
    Activity::query()->delete();

    $justNow = $entry('Account updated', now()->subMinute());
    $lastWeek = $entry('Account updated', now()->subDays(7));

    $component = livewire(ListActivities::class)
        ->assertSee('1m ago')
        ->assertSee('1w ago');

    $component->assertTableColumnHasDescription('created_at', '1m ago', $justNow);
    $component->assertTableColumnHasDescription('created_at', '1w ago', $lastWeek);
    $component->assertTableColumnDoesNotHaveDescription('created_at', '1m ago', $justNow, 'above');

    expect($component->html())->toMatch('/fi-ta-text-description"[^>]*>\s*1m ago\s*<\/p>/u')
        ->and($component->html())->toMatch('/fi-ta-text-description"[^>]*>\s*1w ago\s*<\/p>/u');
});

test('every entry reads as a sentence naming who acted and on what', function () use ($entry): void {
    signIn('Nayra Condori');
    $subject = makeUser('Amaru Quispe');
    $subject->update(['name' => 'Amaru Yupanqui']);

    $wordless = $entry('Nightly retention ran', now(), event: null);

    livewire(ListActivities::class)
        ->assertSeeHtml('<strong>Nayra Condori</strong> changed the name of <strong>Amaru Yupanqui</strong>')
        ->assertSeeHtml('<strong>Nayra Condori</strong> created <strong>Amaru Quispe</strong>')
        ->assertSeeHtml('<strong>Nayra Condori</strong> was created')
        ->assertSee('Nightly retention ran')
        ->assertTableColumnStateSet('event', null, $wordless)
        ->assertSee('—');
});

test('the event of an entry is badged by the kind of event it was', function () use ($entry): void {
    signIn();
    Activity::query()->delete();

    $created = $entry('Account created', now()->subMinutes(5), event: 'created');
    $updated = $entry('Account updated', now()->subMinutes(4), event: 'updated');
    $deleted = $entry('Account deleted', now()->subMinutes(3), event: 'deleted');
    $restored = $entry('Account restored', now()->subMinutes(2), event: 'restored');
    $signedIn = $entry('Signed in', now()->subMinute(), event: 'signed in');

    $component = livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$created, $updated, $deleted, $restored, $signedIn])
        ->assertTableColumnStateSet('event', 'created', $created)
        ->assertTableColumnStateSet('event', 'signed in', $signedIn);

    /** @var ListActivities $page */
    $page = $component->instance();

    /** @var TextColumn $column */
    $column = $page->getTable()->getColumn('event');

    expect($column->isBadge())->toBeTrue()
        ->and($column->getColor('created'))->toBe('success')
        ->and($column->getColor('updated'))->toBe('info')
        ->and($column->getColor('deleted'))->toBe('danger')
        ->and($column->getColor('restored'))->toBe('warning')
        ->and($column->getColor('signed in'))->toBe('gray')
        ->and($column->getColor(null))->toBe('gray');
});

test('the log, the record and the author ship switched off and can be switched on', function (): void {
    signIn('Nayra Condori');
    $subject = makeUser('Amaru Quispe');
    $subject->update(['name' => 'Amaru Yupanqui']);

    $component = livewire(ListActivities::class)
        ->assertCanSeeTableRecords(Activity::query()->get())
        ->assertDontSeeHtml('title="Nayra Condori"');

    /** @var ListActivities $page */
    $page = $component->instance();

    expect(array_keys($page->getTable()->getVisibleColumns()))
        ->toBe(['created_at', 'event', 'description']);

    $component
        ->toggleAllTableColumns()
        ->assertCanSeeTableRecords(Activity::query()->get())
        ->assertSeeHtml('title="Nayra Condori"');

    /** @var ListActivities $toggled */
    $toggled = $component->instance();

    expect(array_keys($toggled->getTable()->getVisibleColumns()))
        ->toBe(['created_at', 'event', 'description', 'log_name', 'subject_type', 'causer_id']);
});

test('both parties of an entry are loaded ahead of the rows that name them', function (): void {
    expect(array_keys(ActivityResource::getEloquentQuery()->getEagerLoads()))
        ->toBe(['subject', 'causer']);
});

test('the log pages by the sizes the configuration names', function (): void {
    signIn();

    $component = livewire(ListActivities::class);

    /** @var ListActivities $page */
    $page = $component->instance();

    expect($page->getTable()->getPaginationPageOptions())->toBe([10, 25, 50]);

    config()->set('filament-activitylog.per_page_options', [5, 15]);

    $reconfigured = livewire(ListActivities::class);

    /** @var ListActivities $reconfiguredPage */
    $reconfiguredPage = $reconfigured->instance();

    expect($reconfiguredPage->getTable()->getPaginationPageOptions())->toBe([5, 15]);
});

test('the log narrows to the days the date filter names', function () use ($entry): void {
    travelTo('2026-08-16 15:00:00');
    signIn();
    Activity::query()->delete();

    $today = $entry('Account updated', now());
    $recent = $entry('Account updated', now()->subDays(3));
    $old = $entry('Account updated', now()->subDays(10));

    livewire(ListActivities::class)
        ->filterTable('logged_at', ['from' => now()->subDays(5)->toDateString()])
        ->assertCanSeeTableRecords([$today, $recent])
        ->assertCanNotSeeTableRecords([$old]);

    livewire(ListActivities::class)
        ->filterTable('logged_at', ['until' => now()->subDay()->toDateString()])
        ->assertCanSeeTableRecords([$recent, $old])
        ->assertCanNotSeeTableRecords([$today]);

    livewire(ListActivities::class)
        ->filterTable('logged_at', [
            'from' => now()->subDays(5)->toDateString(),
            'until' => now()->subDay()->toDateString(),
        ])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$today, $old]);
});

test('the log narrows to the kinds of event the filter names', function () use ($entry): void {
    signIn();
    Activity::query()->delete();

    $created = $entry('Account created', now()->subMinutes(2), event: 'created');
    $deleted = $entry('Account deleted', now()->subMinute(), event: 'deleted');

    livewire(ListActivities::class)
        ->filterTable('event', ['created'])
        ->assertCanSeeTableRecords([$created])
        ->assertCanNotSeeTableRecords([$deleted]);
});

test('searching the log matches the description as it was stored, never the sentence it is read as', function () use ($entry): void {
    $causer = signIn('Nayra Condori');
    $subject = makeUser('Amaru Quispe');
    Activity::query()->delete();

    $passkey = $entry('Passkey revoked', now()->subMinute(), event: 'deleted', subject: $subject, causer: $causer);
    $account = $entry('Account updated', now(), event: 'updated', subject: $subject, causer: $causer);

    livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$passkey, $account])
        ->assertSeeHtml('<strong>Nayra Condori</strong> deleted <strong>Amaru Quispe</strong>');

    livewire(ListActivities::class)
        ->searchTable('Passkey revoked')
        ->assertCanSeeTableRecords([$passkey])
        ->assertCanNotSeeTableRecords([$account]);

    livewire(ListActivities::class)
        ->searchTable('deleted')
        ->assertCanNotSeeTableRecords([$passkey, $account]);

    livewire(ListActivities::class)
        ->searchTable('Amaru Quispe')
        ->assertCanNotSeeTableRecords([$passkey, $account]);

    livewire(ListActivities::class)
        ->searchTable('Nayra Condori')
        ->assertCanNotSeeTableRecords([$passkey, $account]);
});

test('where the log sits in the navigation is the configuration to decide', function (): void {
    expect(ActivityResource::getNavigationIcon())->toBe(Heroicon::OutlinedClock)
        ->and(ActivityResource::getNavigationGroup())->toBeNull()
        ->and(ActivityResource::getNavigationSort())->toBeNull();

    config()->set('filament-activitylog.navigation.icon', 'lucide-history');
    config()->set('filament-activitylog.navigation.group', 'Security');
    config()->set('filament-activitylog.navigation.sort', 40);

    /** @var NavigationItem $item */
    $item = Arr::first(ActivityResource::getNavigationItems());

    expect($item->getIcon())->toBe('lucide-history')
        ->and($item->getGroup())->toBe('Security')
        ->and($item->getSort())->toBe(40);
});

test('the log answers on the slug the configuration names', function (): void {
    expect(ActivityResource::getSlug())->toBe('activity')
        ->and(ActivityResource::getUrl('index'))->toEndWith('/test/activity');

    config()->set('filament-activitylog.navigation.slug', 'audit-trail');

    expect(ActivityResource::getSlug())->toBe('audit-trail');
});

test('the log is kept out of the global search', function (): void {
    signIn();

    expect(ActivityResource::canGloballySearch())->toBeFalse()
        ->and(ActivityResource::getGloballySearchableAttributes())->not->toBeEmpty()
        ->and(ActivityResource::canAccess())->toBeTrue();
});

test('nothing can be created from the log', function (): void {
    signIn();

    expect(ActivityResource::canCreate())->toBeFalse()
        ->and(fn (): mixed => ActivityResource::getAuthorizationResponse('create'))
        ->toThrow(LogicException::class);

    livewire(ListActivities::class)->assertOk();
});

test('an entry is titled by the description as it is said, not as it is stored', function () use ($entry): void {
    signIn();

    $activity = $entry('Cuenta creada', now(), event: 'created');

    expect(ActivityResource::getRecordTitle($activity))->toBe('Cuenta creada');

    config()->set('filament-activitylog.descriptions', ['Cuenta creada' => 'Account created']);

    expect(ActivityResource::getRecordTitle($activity))->toBe('Account created')
        ->and($activity->description)->toBe('Cuenta creada')
        ->and(ActivityResource::getRecordTitle(null))->toBe('Activity');
});

test('the listing is rendered in the language the application speaks', function () use ($entry): void {
    travelTo('2026-08-16 15:00:00');
    app()->setLocale('es');
    signIn();
    Activity::query()->delete();

    $today = $entry('Cuenta actualizada', now());

    $component = livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$today])
        ->assertSee('Hoy · 16 ago. 2026')
        ->assertSee('Creado')
        ->assertSee('Actualizado')
        ->assertSee('Eliminado')
        ->assertSee('Restaurado')
        ->assertDontSee('Today · ')
        ->assertDontSee('Created')
        ->assertDontSee('Updated')
        ->assertDontSee('Deleted')
        ->assertDontSee('Restored');

    expect(ActivityResource::getModelLabel())->toBe('Actividad')
        ->and(ActivityResource::getPluralModelLabel())->toBe('Actividad')
        ->and($component->html())->toMatch('/<h1[^>]*class="fi-header-heading"[^>]*>\s*Actividad\s*<\/h1>/u');

    /** @var ListActivities $page */
    $page = $component->instance();

    /** @var SelectFilter $filter */
    $filter = $page->getTable()->getFilter('event');

    expect($filter->getOptions())->toBe([
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
        'restored' => 'Restaurado',
    ]);
});

test('the detail schema of an entry is the one the package composes', function (): void {
    expect(ActivityResource::infolist(Schema::make())->getComponents())->not->toBeEmpty();
});

test('somebody who may not read the log is refused its listing', function (): void {
    signIn();

    ActivityPolicy::$viewAny = false;

    livewire(ListActivities::class)->assertForbidden();
});

test('an entry whose changed attributes were not stored as a list of fields is listed like any other', function (): void {
    signIn('Nayra Condori');
    $subject = makeUser('Amaru Quispe');
    Activity::query()->delete();

    activity()
        ->on($subject)
        ->event('updated')
        ->withChanges(['attributes' => 'oops'])
        ->log('Imported from the old system');

    $shapeless = lastActivity();

    activity()
        ->on($subject)
        ->event('updated')
        ->withChanges(['attributes' => ['name' => 'Amaru Yupanqui'], 'old' => 'nope'])
        ->log('Imported from the old system');

    $halfShapeless = lastActivity();

    $component = livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$shapeless, $halfShapeless])
        ->assertSeeHtml('<strong>Nayra Condori</strong> updated <strong>Amaru Quispe</strong>')
        ->assertSeeHtml('<strong>Nayra Condori</strong> changed the name of <strong>Amaru Quispe</strong>');

    expect(data_get($shapeless->attribute_changes, 'attributes'))->toBe('oops')
        ->and(data_get($halfShapeless->attribute_changes, 'old'))->toBe('nope');

    $component->assertOk();
});

test('the record of a row is drawn with the icon and the colour the configuration gives it', function () use ($entry): void {
    signIn();
    $subject = makeUser('Amaru Quispe');
    Activity::query()->delete();

    $activity = $entry('Account updated', now(), subject: $subject);

    config()->set('filament-activitylog.records.'.User::class, [
        'icon' => Heroicon::OutlinedUser,
        'color' => 'danger',
    ]);

    $component = livewire(ListActivities::class)
        ->toggleAllTableColumns()
        ->assertCanSeeTableRecords([$activity]);

    expect($component->html())
        ->toContain('color-mix(in oklab, var(--danger-500) 14%, transparent)')
        ->toContain('M15.75 6a3.75 3.75 0 1 1-7.5 0')
        ->not->toContain('m21 7.5-9-5.25L3 7.5')
        ->toContain('<span style="font-weight: 600;">Amaru Quispe</span>')
        ->toContain('<span style="font-size: 0.75rem; color: var(--gray-500);">User</span>');
});

test('the record of a row is still drawn when the configuration names something that is no icon at all', function () use ($entry): void {
    signIn();
    $subject = makeUser('Amaru Quispe');
    Activity::query()->delete();

    $activity = $entry('Account updated', now(), subject: $subject);

    config()->set('filament-activitylog.records.'.User::class, ['icon' => 42]);

    $number = livewire(ListActivities::class)
        ->toggleAllTableColumns()
        ->assertCanSeeTableRecords([$activity]);

    expect($number->html())
        ->toContain('m21 7.5-9-5.25L3 7.5')
        ->toContain('color-mix(in oklab, var(--info-500) 14%, transparent)')
        ->toContain('<span style="font-weight: 600;">Amaru Quispe</span>');

    config()->set('filament-activitylog.records.'.User::class, [
        'icon' => ['heroicon-o-user'],
        'color' => ['danger'],
    ]);

    $list = livewire(ListActivities::class)
        ->toggleAllTableColumns()
        ->assertCanSeeTableRecords([$activity]);

    expect($list->html())
        ->toContain('m21 7.5-9-5.25L3 7.5')
        ->toContain('color-mix(in oklab, var(--gray-500) 14%, transparent)')
        ->toContain('<span style="font-weight: 600;">Amaru Quispe</span>');

    $list->assertOk();
});

test('the badge of an event reads the word the filter that selects it offers', function () use ($entry): void {
    signIn();
    Activity::query()->delete();

    $created = $entry('Account created', now()->subMinutes(3), event: 'created');
    $updated = $entry('Account updated', now()->subMinutes(2), event: 'updated');
    $signedIn = $entry('Signed in', now()->subMinute(), event: 'signed in');

    $english = livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$created, $updated, $signedIn]);

    expect($english->html())
        ->toMatch('/fi-badge[^>]*fi-color-success[^>]*>\s*Created\s*</u')
        ->toMatch('/fi-badge[^>]*fi-color-info[^>]*>\s*Updated\s*</u')
        ->toMatch('/fi-badge[^>]*>\s*signed in\s*</u');

    app()->setLocale('es');

    $spanish = livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$created, $updated, $signedIn])
        ->assertDontSee('Created')
        ->assertDontSee('Updated');

    expect($spanish->html())
        ->toMatch('/fi-badge[^>]*fi-color-success[^>]*>\s*Creado\s*</u')
        ->toMatch('/fi-badge[^>]*fi-color-info[^>]*>\s*Actualizado\s*</u')
        ->toMatch('/fi-badge[^>]*>\s*signed in\s*</u');

    /** @var ListActivities $page */
    $page = $spanish->instance();

    /** @var SelectFilter $filter */
    $filter = $page->getTable()->getFilter('event');

    expect($filter->getOptions())->toMatchArray([
        'created' => 'Creado',
        'updated' => 'Actualizado',
    ]);

    livewire(ListActivities::class)
        ->filterTable('event', ['updated'])
        ->assertCanSeeTableRecords([$updated])
        ->assertCanNotSeeTableRecords([$created, $signedIn]);
});
