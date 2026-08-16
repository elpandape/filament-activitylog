<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users;

use ElPandaPe\FilamentActivitylog\Filament\Actions\ActivityAction;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\Pages\ListUsers;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Filament\Resources\Users\Pages\ViewUser;
use ElPandaPe\FilamentActivitylog\Tests\Fixtures\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The resource the suite hands the package so it has somewhere to link a record to, and
 * somewhere to hang the activity drawer.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActivityAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
