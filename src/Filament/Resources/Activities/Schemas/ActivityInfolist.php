<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                ViewEntry::make('event')
                                    ->hiddenLabel()
                                    ->view('filament-activitylog::infolists.headline'),
                            ]),

                        Section::make(__('filament-activitylog::ui.detail.changes'))
                            ->description(__('filament-activitylog::ui.detail.changes_hint'))
                            ->schema([
                                ViewEntry::make('attribute_changes')
                                    ->hiddenLabel()
                                    ->view('filament-activitylog::infolists.changes'),
                            ]),

                    ])
                    ->columnSpan(['lg' => 3]),

                Group::make()
                    ->schema([
                        Section::make(__('filament-activitylog::ui.detail.context'))
                            ->schema([
                                ViewEntry::make('log_name')
                                    ->hiddenLabel()
                                    ->view('filament-activitylog::infolists.context'),
                            ]),

                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(4);
    }
}
