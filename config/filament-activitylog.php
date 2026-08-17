<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Logging\CauserRoleFromRelation;
use Filament\Support\Icons\Heroicon;

return [

    /*
     * How the activity resource presents itself inside the panel. These are the consuming
     * application's decisions, not the package's: the group may be called something else,
     * the order depends on what else that panel holds, and the icon on whichever family
     * that project already uses.
     */
    'navigation' => [
        'icon' => Heroicon::OutlinedClock,
        'group' => null,
        'sort' => null,
        'slug' => 'activity',
    ],

    /*
     * How dates are written across the resource. The listing groups by day and shows only
     * the clock on each row, so `date` is what the day separator reads.
     */
    'formats' => [
        'time' => 'H:i',
        'date' => 'j M Y',
        'datetime' => 'j M Y H:i:s',
    ],

    'per_page_options' => [10, 25, 50],

    /*
     * Attributes whose values must never be written or shown, whatever model they belong
     * to. An entry still says the attribute changed — hiding that would be a lie — while
     * the value is replaced before the row is written and rendered as dots on screen.
     */
    'masked' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],

    /*
     * What this package does to every row on its way to the database. Both hooks hang off
     * `beforeLogging`, so neither replaces the action that writes the row and a custom one
     * keeps working.
     */
    'logging' => [
        /*
         * Writes down the names the record and the author had at that instant. Without it
         * an entry about a deleted record stops naming it: the columns are pointers, and a
         * pointer to a deleted row answers nothing.
         */
        'seal_actors' => true,

        /*
         * Replaces the value of every masked attribute before the row is written, so a
         * password hash never reaches the database. Turning it off leaves `masked` as a
         * screen-only measure.
         */
        'mask_secrets' => true,

        /*
         * Which role the author acted with, sealed alongside the names and for the same
         * reason: taking a role away later must not rewrite what its holder did.
         *
         * The default reads a `roles` relation on the causer's own model and names what it
         * finds through `records`, like every other record here; a model with no such
         * relation answers nothing. Roles that are not a relation need a class of your own
         * implementing `Contracts\ResolvesCauserRole`; null asks nobody at all.
         *
         * A class name and not a closure because this file is cached, and `config:cache`
         * chokes on a closure.
         */
        /*
         * Writes down where a request came from, in the three keys the entry page's context rail
         * reads: the client address, the method and path, and the user agent. Without it that rail
         * stays empty unless the application passes those keys itself, everywhere it logs.
         *
         * Keys the caller already set are left alone, and nothing is written where there is no
         * client address — a console command, a queued job, a scheduled task — because a bare
         * `GET /` would say a request happened when none did.
         */
        'stamp_request' => true,

        'causer_role' => CauserRoleFromRelation::class,
    ],

    /*
     * How each kind of record shows, keyed by whatever the log stores in `subject_type` —
     * the class name, or the alias when the application uses a morph map. The icon lets a
     * reader tell a passkey from an account without reading, the colour is any of the
     * panel's registered aliases, and `name` is the attribute a record is named by, for
     * models that call it something other than `name`.
     *
     * A model that is not listed falls back to a neutral icon and to `name`. This is also
     * where a role model is told what it is called, which is what the default role resolver
     * reads. Plain values only: this file is cached, and `config:cache` chokes on a closure.
     */
    'records' => [
        // \App\Models\User::class => ['icon' => Heroicon::OutlinedUser, 'color' => 'info', 'name' => 'full_name'],
    ],

    /*
     * How a stored description is said on screen, keyed by the string the application
     * wrote. Translating on read is what keeps an audit trail honest: the row keeps the
     * words it was written with, and nobody rewrites the past. A description that is not
     * listed shows as it was stored.
     */
    'descriptions' => [
        // 'Cuenta creada' => 'Account created',
    ],

    /*
     * The drawer that tells a record's history from a table row or a page header. It is a
     * glance, not an investigation: past this many entries it says how many there are and
     * points at the full log.
     */
    'timeline' => [
        'limit' => 25,

        /*
         * How that history reads. `classic` puts each entry on its own row with a medallion,
         * its clock and its distance; `thread` tells it as one continuous line, with the
         * large node kept for what opens or closes a record's life and the small one for an
         * attribute change, and the distance alone on the right.
         *
         * Anything else falls back to `classic`.
         */
        'style' => 'classic',
    ],

];
