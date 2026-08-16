<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Pages;

use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;
}
