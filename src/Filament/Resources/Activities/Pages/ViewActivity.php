<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Pages;

use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ViewRecord;

class ViewActivity extends ViewRecord
{
    protected static string $resource = ActivityResource::class;

    public function getTitle(): string
    {
        $key = $this->getRecord()->getKey();

        return __('filament-activitylog::ui.detail.title', [
            'id' => is_scalar($key) ? (string) $key : '?',
        ]);
    }
}
