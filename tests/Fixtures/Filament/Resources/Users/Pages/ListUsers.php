<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\Pages;

use ElPandaPe\FilamentActivitylog\Filament\Actions\ActivityAction;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * The drawer hung where no record is in scope, which is what a listing header is: the
     * README invites it, and the action has to answer with its empty state rather than fail
     * on a null it was not typed for.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActivityAction::make(),
        ];
    }
}
