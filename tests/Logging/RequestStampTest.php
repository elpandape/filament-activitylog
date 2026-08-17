<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Tests\TestCase;

pest()->extend(TestCase::class);

test('an entry written while somebody is asking says where they asked from', function (): void {
    askingFrom(agent: 'Chrome of somebody');

    makeUser('Amaru Quispe');

    $entry = lastActivity();

    expect($entry->getProperty('ip'))->toBe('203.0.113.7')
        ->and($entry->getProperty('agent'))->toBe('Chrome of somebody')
        ->and($entry->getProperty('via'))->toBeString();
});

test('an entry written with nobody asking carries no request at all', function (): void {
    askingFromNobody();

    makeUser('Amaru Quispe');

    $entry = lastActivity();

    expect($entry->getProperty('ip'))->toBeNull()
        ->and($entry->getProperty('via'))->toBeNull()
        ->and($entry->getProperty('agent'))->toBeNull();
});

test('a bare GET of nothing is never written, because that is what a command would look like', function (): void {
    askingFromNobody();

    makeUser('Amaru Quispe');

    expect(lastActivity()->getProperty('via'))->not->toBe('GET /');
});

test('what the caller said about a request is left alone', function (): void {
    askingFrom(agent: 'Chrome of somebody');

    activity()->withProperties([
        'ip' => '198.51.100.4',
        'via' => 'POST somewhere/else',
    ])->log('Something happened');

    $entry = lastActivity();

    expect($entry->getProperty('ip'))->toBe('198.51.100.4')
        ->and($entry->getProperty('via'))->toBe('POST somewhere/else')
        ->and($entry->getProperty('agent'))->toBe('Chrome of somebody');
});

test('the stamp can be turned off, and then nothing fills the rail', function (): void {
    config()->set('filament-activitylog.logging.stamp_request', false);

    askingFrom(agent: 'Chrome of somebody');

    makeUser('Amaru Quispe');

    expect(lastActivity()->getProperty('ip'))->toBeNull();
});

test('an agent that is missing is left out rather than written as nothing', function (): void {
    askingFrom();

    makeUser('Amaru Quispe');

    $entry = lastActivity();

    expect($entry->getProperty('ip'))->toBe('203.0.113.7')
        ->and($entry->getProperty('agent'))->toBeNull();
});
