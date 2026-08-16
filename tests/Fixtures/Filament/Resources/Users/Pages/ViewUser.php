<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\Pages;

use ElPandaPe\FilamentActivitylog\Filament\Actions\ActivityAction;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            ActivityAction::make(),
        ];
    }
}
