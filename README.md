# filament-activitylog

An activity log panel for [Filament](https://filamentphp.com), built on
[spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) v5.

Two screens and a drawer, none of which can write an entry — and two hooks that improve the
entries somebody else writes.

- **A listing grouped by day.** Each entry reads as a sentence — "Nayra Condori changed the
  name of Amaru Quispe" — with the clock and the event beside it. The log, the record and
  the author are there too, switched off by default: the sentence already names them.
- **The entry itself.** What happened, who did it, and what changed field by field, with the
  old value and the new one side by side. Sensitive attributes say that they changed and
  never what to.
- **A record's history, in a drawer.** Hang `ActivityAction` on a table row or a page header
  and it tells that record's story without leaving the screen. It reads either as a list of
  rows or as one continuous thread; both are the same sentences.

What it adds to the rows themselves, both switchable:

- **Names are sealed into the row.** `subject_id` and `causer_id` are pointers, and a
  pointer to a deleted row answers nothing — so an entry keeps the names its parties had at
  that instant. Delete an administrator and their history still says who they were.
- **Secrets never reach the database.** A masked attribute is replaced before the row is
  written, so a model can log a password and the log will say it changed without saying to
  what.

## Requirements

PHP 8.5 · Laravel 13 · Filament 5.7 · spatie/laravel-activitylog 5.1

## Installation

```bash
composer require elpandape/filament-activitylog
```

`activitylog` ships its migration as a stub and never loads it from `vendor`, so publish it
if you have not already:

```bash
php artisan vendor:publish --tag=activitylog-migrations
php artisan migrate
```

Register the plugin on your panel:

```php
use ElPandaPe\FilamentActivitylog\FilamentActivitylogPlugin;

$panel->plugin(FilamentActivitylogPlugin::make());
```

Publish what you want to change:

```bash
php artisan vendor:publish --tag=filament-activitylog-config
php artisan vendor:publish --tag=filament-activitylog-translations
php artisan vendor:publish --tag=filament-activitylog-views
```

There are no assets to publish. The package ships no stylesheet: every colour it paints comes
from the panel's own palette variables, so it follows your theme in light and dark without a
build step.

## Authorization

The model is `Spatie\Activitylog\Models\Activity`, which lives in vendor: Laravel's policy
guessing never finds a policy for it, so the consuming application must register one.

```php
Gate::policy(Activity::class, ActivityPolicy::class);
```

Declare `viewAny` and `view` and nothing else. Under Filament's `->strictAuthorization()` an
absent method is not a denied permission but an impossible action — which is exactly what
keeps an audit trail from being created, edited or deleted from a screen. Entries leave by
retention, with `activitylog:clean`.

The package takes no stance on how that policy decides; pair it with whatever authorization
layer the application already uses. The drawer asks the same gate: somebody who may see your
users but not the log is not offered it. If you set `activitylog.activity_model` to a model of
your own, register the policy against that class — the resource and the drawer both follow
that configuration.

## The drawer

```php
use ElPandaPe\FilamentActivitylog\Filament\Actions\ActivityAction;

// in a table
->recordActions([ActivityAction::make()])

// in a page header
protected function getHeaderActions(): array
{
    return [ActivityAction::make()];
}
```

It is a glance, not an investigation: it shows the most recent `timeline.limit` entries and
then says how many there are in total. `timeline.style` picks how it reads — `classic` gives
each entry a row with its medallion and its clock, `thread` tells it as one continuous line
where a large node marks what opens or closes a record's life and a small one an attribute
change.

Each entry offers a link to its own page when the plugin is registered on that panel and the
reader is allowed to open it; otherwise the entry number is shown without one.

## What the package writes into a row

Both hooks hang off `beforeLogging`, which runs before the row is saved. Neither replaces
`activitylog.actions.log_activity`, so an application that already swapped that class keeps
it — as long as the subclass does not redeclare `$beforeLoggingCallbacks`, which would empty
the list and silently stop both.

**`logging.seal_actors`** writes `properties.actors`:

```json
{"actors": {"subject": "Amaru Quispe", "causer": "Nayra Condori", "causer_role": "Super admin"}}
```

The names the subject and the causer had at that instant, and the role the causer acted with.
Proper names go in the row; the grammar stays in the code, so fixing a typo never means
rewriting the past. A missing key is information — it tells "there was no author" apart from
"this row predates the seal", which falls back to the relation. That shape is public API from
1.0.0.

The role is read by default from a `roles` relation on the causer's own model, and whatever
it finds there is named the same way every other record is: through `records`. So a role
called by something other than `name` is declared once —

```php
'records' => [Role::class => ['name' => 'title']],
```

— and a model with no such relation answers nothing. It costs one query per entry written;
`'causer_role' => null` turns it off.

Roles that are not a relation — a column, a service, a claim in a token — need a class of
your own:

```php
use ElPandaPe\FilamentActivitylog\Contracts\ResolvesCauserRole;
use Illuminate\Database\Eloquent\Model;

final class RoleOfCauser implements ResolvesCauserRole
{
    public function __invoke(Model $causer): ?string
    {
        return $causer->getAttributes()['role'] ?? null;
    }
}
```

```php
'logging' => ['causer_role' => RoleOfCauser::class],
```

**`logging.mask_secrets`** replaces the value of every attribute in `masked`, in both halves
of the change set, before the row is written. Without it `masked` is a screen-only measure
and the hash still lands in your database. Adding a sensitive attribute to a model's
`logOnly()` means adding it to `masked` as well; nothing enforces that pairing for you. It
covers the change set only — anything you pass to `withProperties()` is written as given.

## Configuration

| Key | What it decides |
| --- | --- |
| `navigation` | Icon, group, sort and slug of the resource. Heroicon by default; swap in whichever family your panel uses. |
| `formats` | How times, dates and timestamps are written. |
| `per_page_options` | The page sizes the listing offers. |
| `masked` | Attributes whose values are never written and never shown, whatever model they belong to. |
| `logging.seal_actors` | Whether the names of the parties are written into the row. |
| `logging.mask_secrets` | Whether masked values are replaced before the row is written. |
| `logging.causer_role` | Who answers which role the author acted with. Defaults to reading a `roles` relation on the causer and naming it through `records`; null asks nobody. |
| `records` | Per record type: the icon and colour it shows with, and the attribute it is named by — roles included. Keyed by whatever your log stores in `subject_type` — the class name, or the alias if you use a morph map. |
| `descriptions` | How a stored description is said on screen. Translating on read keeps the row honest. |
| `timeline.limit` · `timeline.style` | How many entries the drawer shows, and whether it reads as rows or as a thread. |

## The context rail

The entry page shows where a request came from, reading `properties.ip`, `properties.via` and
`properties.agent`. Nothing writes them for you — neither this package nor `activitylog` — so
until your application stamps them, every entry says it happened outside a web request:

```php
activity()
    ->withProperties([
        'ip' => request()->ip(),
        'via' => request()->method().' '.request()->path(),
        'agent' => request()->userAgent(),
    ])
    ->log('…');
```

## Languages

Ships English and Spanish, and every label, column and filter is translated. Two things are
not, and both are deliberate:

- **The sentence is composed in English.** `Narrative` builds its grammar in code — "changed
  the name of", the passive for an entry with no author — and word order does not survive
  translation by string replacement. A Spanish panel reads its own labels and English
  sentences until that is designed properly.
- **Dates follow Carbon's locale, not Laravel's.** `App::setLocale()` does not reach Carbon;
  call `Carbon::setLocale()` too if you want day separators in your language.

## Not here yet

Restoring a record from an entry, and grouping related entries into one block. The second one
needs the application to stamp a property of its own: `activitylog` v5 removed batches, and
nothing else says two entries belong to the same event.

Search runs over the stored `description` column, not over the composed sentence — so a name
you can read on screen is not necessarily a name you can search for.

## License

MIT. See [LICENSE.md](LICENSE.md).
