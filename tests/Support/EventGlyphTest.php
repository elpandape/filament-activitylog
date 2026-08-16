<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Support\EventGlyph;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Filament\Support\Icons\Heroicon;

pest()->extend(TestCase::class);

test('a created entry is green, opens with a plus and is not minor', function (): void {
    makeUser();

    $glyph = EventGlyph::of(lastActivity());

    expect($glyph->color)->toBe('success')
        ->and($glyph->icon)->toBe(Heroicon::OutlinedPlusCircle)
        ->and($glyph->label)->toBe('Created')
        ->and($glyph->minor)->toBeFalse();
});

test('an updated entry is blue and carries the arrow that says something changed', function (): void {
    $user = makeUser();
    $user->update(['name' => 'Amaru Yupanqui']);

    $glyph = EventGlyph::of(lastActivity());

    expect($glyph->color)->toBe('info')
        ->and($glyph->icon)->toBe(Heroicon::OutlinedArrowPath)
        ->and($glyph->label)->toBe('Updated')
        ->and($glyph->minor)->toBeTrue();
});

test('a deleted entry is red, carries a bin and is not minor', function (): void {
    $user = makeUser();
    $user->delete();

    $glyph = EventGlyph::of(lastActivity());

    expect($glyph->color)->toBe('danger')
        ->and($glyph->icon)->toBe(Heroicon::OutlinedTrash)
        ->and($glyph->label)->toBe('Deleted')
        ->and($glyph->minor)->toBeFalse();
});

test('a restored entry is amber, turns back and is not minor', function (): void {
    activity()->event('restored')->log('Account restored');

    $glyph = EventGlyph::of(lastActivity());

    expect($glyph->color)->toBe('warning')
        ->and($glyph->icon)->toBe(Heroicon::OutlinedArrowUturnLeft)
        ->and($glyph->label)->toBe('Restored')
        ->and($glyph->minor)->toBeFalse();
});

test('an event the package knows nothing about is neutral and keeps its own name', function (): void {
    activity()->event('signed in')->log('Signed in');

    $glyph = EventGlyph::of(lastActivity());

    expect($glyph->color)->toBe('gray')
        ->and($glyph->icon)->toBe(Heroicon::OutlinedBolt)
        ->and($glyph->label)->toBe('signed in')
        ->and($glyph->minor)->toBeFalse();
});

test('an attribute change is the only kind of entry the timeline treats as minor', function (): void {
    $user = makeUser();
    $created = EventGlyph::of(lastActivity());

    $user->update(['name' => 'Amaru Yupanqui']);
    $updated = EventGlyph::of(lastActivity());

    $user->delete();
    $deleted = EventGlyph::of(lastActivity());

    activity()->event('restored')->log('Account restored');
    $restored = EventGlyph::of(lastActivity());

    activity()->event('signed in')->log('Signed in');
    $unknown = EventGlyph::of(lastActivity());

    expect($updated->minor)->toBeTrue()
        ->and($created->minor)->toBeFalse()
        ->and($deleted->minor)->toBeFalse()
        ->and($restored->minor)->toBeFalse()
        ->and($unknown->minor)->toBeFalse();
});

test('an entry written without an event is labelled with a word, never with a blank', function (): void {
    activity()->log('Something happened');

    $glyph = EventGlyph::of(lastActivity());

    expect(lastActivity()->event)->toBeNull()
        ->and($glyph->label)->toBe('entry')
        ->and($glyph->color)->toBe('gray')
        ->and($glyph->icon)->toBe(Heroicon::OutlinedBolt);
});

test('an entry whose event is an empty string is labelled like one with no event at all', function (): void {
    activity()->event('')->log('Something happened');

    $glyph = EventGlyph::of(lastActivity());

    expect(lastActivity()->event)->toBe('')
        ->and($glyph->label)->toBe('entry');
});

test('the label of an entry without an event is said in the language of the panel', function (): void {
    activity()->log('Something happened');

    $activity = lastActivity();

    expect(EventGlyph::of($activity)->label)->toBe('entry');

    app()->setLocale('es');

    expect(EventGlyph::of($activity)->label)->toBe('asiento');
});
