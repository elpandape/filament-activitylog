<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Support\RecordName;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\Article;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Illuminate\Support\Str;

pest()->extend(TestCase::class);

test('a record with nothing configured is named by its name attribute', function (): void {
    expect(RecordName::of(makeUser('Amaru Quispe')))->toBe('Amaru Quispe');
});

test('a record is named by the attribute its model is configured with', function (): void {
    config()->set('filament-activitylog.records.'.Article::class.'.name', 'title');

    $article = Article::query()->create(['title' => 'La casa de carton']);

    expect(RecordName::of($article))->toBe('La casa de carton');
});

test('the configured attribute wins over an attribute called name', function (): void {
    config()->set('filament-activitylog.records.'.User::class.'.name', 'email');

    $user = makeUser('Amaru Quispe');

    expect(RecordName::of($user))->toBe($user->getAttribute('email'))
        ->and(RecordName::of($user))->not->toBe('Amaru Quispe');
});

test('a record that carries no such attribute has no name', function (): void {
    $article = Article::query()->create(['title' => 'La casa de carton']);

    expect(RecordName::of($article))->toBeNull();
});

test('a record whose name is empty has no name', function (): void {
    $user = User::forceCreate([
        'name' => '',
        'email' => Str::random(12).'@example.test',
        'password' => 'irrelevant',
    ]);

    expect(RecordName::of($user))->toBeNull();
});

test('a record named by something that is not text has no name', function (): void {
    config()->set('filament-activitylog.records.'.Article::class.'.name', 'id');

    $article = Article::query()->create(['title' => 'La casa de carton']);

    expect($article->getAttribute('id'))->toBeInt()
        ->and(RecordName::of($article))->toBeNull();
});

test('a blank naming attribute falls back to name', function (): void {
    config()->set('filament-activitylog.records.'.User::class.'.name', '');

    expect(RecordName::of(makeUser('Amaru Quispe')))->toBe('Amaru Quispe');
});

test('a naming attribute that is not text falls back to name', function (): void {
    config()->set('filament-activitylog.records.'.User::class.'.name', ['title']);

    expect(RecordName::of(makeUser('Amaru Quispe')))->toBe('Amaru Quispe');
});

test('a record that was never saved is named all the same', function (): void {
    expect(RecordName::of(new Article(['title' => 'La casa de carton'])))->toBeNull()
        ->and(RecordName::of(new User(['name' => 'Amaru Quispe'])))->toBe('Amaru Quispe');
});
