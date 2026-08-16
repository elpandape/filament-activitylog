<?php

declare(strict_types=1);

use ElPandaPe\FilamentActivitylog\Support\RecordUrl;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\UserResource;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\Article;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Policies\UserPolicy;
use ElPandaPe\FilamentActivitylog\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Resources\Resource;

pest()->extend(TestCase::class);

test('a record the panel shows leads to the relative url of its view page', function (): void {
    signIn();
    $user = makeUser('Amaru Quispe');
    $other = makeUser('Killa Mamani');

    expect(RecordUrl::for($user))->toStartWith('/test/users/')
        ->and(RecordUrl::for($user))->toBe(UserResource::getUrl('view', ['record' => $user], isAbsolute: false))
        ->and(RecordUrl::for($other))->not->toBe(RecordUrl::for($user));
});

test('anything that is not a record leads nowhere', function (): void {
    expect(RecordUrl::for(null))->toBeNull()
        ->and(RecordUrl::for('User #7'))->toBeNull();
});

test('a record that no longer exists leads nowhere', function (): void {
    signIn();
    $user = makeUser();
    $user->delete();

    $activity = lastActivity()->load('subject');

    expect($activity->event)->toBe('deleted')
        ->and($activity->getAttribute('subject'))->toBeNull()
        ->and(RecordUrl::for($activity->getAttribute('subject')))->toBeNull()
        ->and(RecordUrl::for($user))->toBeString();
});

test('a model no resource shows leads nowhere', function (): void {
    signIn();
    $article = Article::forceCreate(['title' => 'Camino inca']);

    expect(Filament::getModelResource($article))->toBeNull()
        ->and(RecordUrl::for($article))->toBeNull()
        ->and(RecordUrl::for(makeUser()))->toBeString();
});

test('a panel that answers with a class that is not a resource leads nowhere', function (): void {
    signIn();
    $article = Article::forceCreate(['title' => 'Camino inca']);

    $impostor = new class
    {
        public static function getModel(): string
        {
            return Article::class;
        }
    };

    Filament::getCurrentPanel()?->resources([$impostor::class]);

    expect(Filament::getModelResource($article))->toBeString()
        ->toBe($impostor::class)
        ->and(RecordUrl::for($article))->toBeNull()
        ->and(RecordUrl::for(makeUser()))->toBeString();
});

test('a resource without a view page leads nowhere', function (): void {
    signIn();
    $article = Article::forceCreate(['title' => 'Camino inca']);

    $resource = new class extends Resource
    {
        protected static ?string $model = Article::class;
    };

    Filament::getCurrentPanel()?->resources([$resource::class]);

    expect(Filament::getModelResource($article))->toBe($resource::class)
        ->and($resource::hasPage('view'))->toBeFalse()
        ->and(RecordUrl::for($article))->toBeNull();
});

test('the view page is asked for before the policy, so a record nobody wrote a policy for cannot blow up a link', function (): void {
    signIn();
    $article = Article::forceCreate(['title' => 'Camino inca']);

    $resource = new class extends Resource
    {
        protected static ?string $model = Article::class;
    };

    Filament::getCurrentPanel()?->resources([$resource::class]);

    expect(Filament::getModelResource($article))->toBe($resource::class)
        ->and(RecordUrl::for($article))->toBeNull()
        ->and(fn (): bool => $resource::canView($article))->toThrow(LogicException::class);
});

test('a reader who may not see the record is given no link to it', function (): void {
    signIn();
    $user = makeUser();

    expect(RecordUrl::for($user))->toBeString();

    UserPolicy::$view = false;

    expect(RecordUrl::for($user))->toBeNull();
});
