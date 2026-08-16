<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

pest()->extend(TestCase::class);

/**
 * @return array<string, string>
 */
function shippedUiLines(string $locale): array
{
    /** @var array<string, mixed> $file */
    $file = require dirname(__DIR__)."/lang/{$locale}/ui.php";

    /** @var array<string, string> $lines */
    $lines = Arr::dot($file);

    return $lines;
}

/**
 * @return list<string>
 */
function uiKeysReferencedBySource(): array
{
    $root = dirname(__DIR__);

    $keys = [];

    foreach ([$root.'/src', $root.'/resources/views'] as $directory) {
        foreach (File::allFiles($directory) as $file) {
            preg_match_all('/filament-activitylog::ui\.([a-zA-Z0-9_.]+)/', $file->getContents(), $matches);

            $keys = [...$keys, ...$matches[1]];
        }
    }

    sort($keys);

    return array_values(array_unique($keys));
}

test('the english and the spanish file declare exactly the same keys', function (): void {
    $english = array_keys(shippedUiLines('en'));
    $spanish = array_keys(shippedUiLines('es'));

    sort($english);
    sort($spanish);

    expect($english)->toContain('model', 'today', 'columns.time', 'actions.heading', 'detail.title')
        ->and($english)->toBe($spanish);
});

test('no line in either language is left empty', function (): void {
    $languages = ['en' => shippedUiLines('en'), 'es' => shippedUiLines('es')];

    expect($languages['en'])->not->toBeEmpty()
        ->and($languages['es'])->not->toBeEmpty();

    foreach ($languages as $locale => $lines) {
        foreach ($lines as $key => $value) {
            expect(mb_trim($value))->not->toBe('', "{$locale}.{$key} is empty");
        }
    }
});

test('every key the source asks for is declared in both languages', function (): void {
    $keys = uiKeysReferencedBySource();

    expect($keys)->toContain('actions.activity', 'columns.subject', 'detail.no_event', 'detail.origin', 'today');

    $english = shippedUiLines('en');
    $spanish = shippedUiLines('es');

    foreach ($keys as $key) {
        expect($english)->toHaveKey($key, message: "en is missing {$key}, asked for by the source")
            ->and($spanish)->toHaveKey($key, message: "es is missing {$key}, asked for by the source");
    }
});

test('every line the language files declare is read by the source', function (): void {
    $referenced = uiKeysReferencedBySource();

    expect($referenced)->not->toBeEmpty();

    foreach (['en' => shippedUiLines('en'), 'es' => shippedUiLines('es')] as $locale => $lines) {
        $declared = array_keys($lines);

        expect($declared)->not->toBeEmpty();

        $unread = array_values(array_diff($declared, $referenced));

        expect($unread)->toBe([], "{$locale} declares lines nobody reads: ".implode(', ', $unread));
    }
});

test('every key the source asks for resolves to its own translation instead of echoing itself', function (): void {
    $keys = uiKeysReferencedBySource();

    expect($keys)->not->toBeEmpty();

    foreach (['en' => shippedUiLines('en'), 'es' => shippedUiLines('es')] as $locale => $lines) {
        foreach ($keys as $key) {
            $resolved = trans("filament-activitylog::ui.{$key}", [], $locale);

            expect($resolved)->toBeString("{$locale} resolved {$key} to something that is not a line")
                ->and($resolved)->not->toBe("filament-activitylog::ui.{$key}", "{$locale} echoed the key {$key} back")
                ->and($resolved)->toBe($lines[$key] ?? null, "{$locale} did not answer with its own line for {$key}");
        }
    }
});

test('both languages carry the same placeholders in every line', function (): void {
    $english = shippedUiLines('en');
    $spanish = shippedUiLines('es');

    $placeholders = 0;

    foreach ($english as $key => $value) {
        preg_match_all('/:[a-zA-Z_]+/', $value, $inEnglish);
        preg_match_all('/:[a-zA-Z_]+/', $spanish[$key] ?? '', $inSpanish);

        expect($inSpanish[0])->toEqualCanonicalizing($inEnglish[0], "the placeholders of {$key} differ between languages");

        $placeholders += count($inEnglish[0]);
    }

    expect($placeholders)->toBeGreaterThan(0);
});
