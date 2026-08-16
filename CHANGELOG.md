# Changelog

All notable changes to `elpandape/filament-activitylog` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). From `1.0.0`,
breaking the public API takes a major bump — and the shape of what is written into
`properties.actors` counts as public API, because it is rows in somebody's database.

## [Unreleased]

## [1.1.0] - 2026-08-16

### Added

- **A role resolver that works out of the box.** The role an author acted with is now read
  from the causer's own `roles` relation — the shape Bouncer and `spatie/laravel-permission`
  both leave on a model — so an application gets it sealed without writing a class. One that
  keeps roles somewhere else still names a `ResolvesCauserRole` of its own, and
  `'causer_role' => null` asks nobody.

### Changed

- `logging.causer_role` defaults to that resolver rather than to nothing, so an entry written
  by somebody holding a role now seals it where it used to seal only their name. It costs one
  query per entry written.

## [1.0.0] - 2026-08-16

First release. The package was built and validated inside one application before being cut
loose; this is the point where it stops depending on that application to be true.

### Added

- **A listing grouped by day**, where every entry reads as a sentence naming both parties.
  The clock and the event travel with it; the log, the record and the author ship switched
  off, because the sentence already says them better than a column beside it would.
- **The entry itself**, in the panel's three-plus-one layout: what happened and who, then
  what changed field by field with the old value against the new, and the request context in
  the narrow rail. A masked attribute is named and never valued — hiding that it changed
  would be a lie, showing the value would be a leak.
- **`ActivityAction`**, a drawer that tells a record's history from a table row or a page
  header. Consulting an account's past happens while working with it, and sending the reader
  to another screen costs them their filter and their scroll. It reads either as rows
  (`classic`) or as one continuous thread (`thread`), and both are composed from the same
  sentences, so two screens cannot tell the same fact differently.
- **The seal of names.** Every row records the names its subject and causer had at that
  instant, plus the role the causer acted with, under `properties.actors`:
  `{"subject": "…", "causer": "…", "causer_role": "…"}`. Without it, deleting an administrator turns
  their whole history into "System" — right about the person somebody deletes when there is
  something to investigate. The role comes from a class the application names, since the
  package knows no authorization system.
- **Masking on write.** The values of `masked` attributes are replaced before the row
  reaches the database, so a model may log an attribute it cannot store. Both halves of the
  change set are covered: the old value is as secret as the new one.
- **Per-model presentation**: the icon and colour a record shows with, and the attribute it
  is named by, for models that do not call it `name`. Keyed by whatever the log stores in
  `subject_type`, so a morph map works.
- **Publish groups** for the configuration, the language files and the views, so any of the
  three can be overridden.
- The resource and the drawer follow `activitylog.activity_model`, so an application that
  subclassed the model registers one policy — against its own class — and both screens ask
  about it.
- **Descriptions translated on read.** The row keeps the words it was written with and
  nobody rewrites the past; the map only decides how they are said.
- English and Spanish translations, and no stylesheet: every colour comes from the panel's
  own palette variables, so the package follows the theme in light and dark without asking
  for a build step.

### Notes

- The `Activity` model lives in vendor, so Laravel's policy guessing never finds a policy for
  it. Registering one is on the application, and under `->strictAuthorization()` a policy
  that declares only `viewAny` and `view` is what makes the log impossible to edit from a
  screen rather than merely forbidden.
- Both write-side behaviours hang off `beforeLogging` rather than replacing
  `activitylog.actions.log_activity`, so an application that already swapped that class keeps
  it and still gets both — unless that subclass redeclares `$beforeLoggingCallbacks`, which
  empties the list and silently stops them.
- The sentence a listing row reads is composed in English. Every label, column and filter is
  translated; the grammar is not, because word order does not survive string replacement.
- Dates follow Carbon's locale, which `App::setLocale()` does not reach.

[Unreleased]: https://github.com/elpandape/filament-activitylog/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/elpandape/filament-activitylog/releases/tag/v1.1.0
[1.0.0]: https://github.com/elpandape/filament-activitylog/releases/tag/v1.0.0
