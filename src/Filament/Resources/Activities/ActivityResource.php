<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Filament\Resources\Activities;

use BackedEnum;
use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Pages\ListActivities;
use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Pages\ViewActivity;
use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Schemas\ActivityInfolist;
use ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Tables\ActivitiesTable;
use ElPandaPe\FilamentActivitylog\Support\StoredDescription;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Exceptions\InvalidConfiguration;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Support\Config;
use UnitEnum;

/**
 * The activity log as a Filament resource: a list and a detail page, nothing else.
 * Navigation group, icon, sort and slug all come from the published config.
 */
class ActivityResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'description';

    protected static bool $isGloballySearchable = false;

    /**
     * @throws InvalidConfiguration
     */
    public static function getModel(): string
    {
        /** @var class-string<Model> $model */
        $model = Config::activityModel();

        return $model;
    }

    public static function getDefaultSlug(): string
    {
        /** @var string $slug */
        $slug = config('filament-activitylog.navigation.slug', 'activity');

        return $slug;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        /** @var string|BackedEnum|null $icon */
        $icon = config('filament-activitylog.navigation.icon', Heroicon::OutlinedClock);

        return $icon;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        /** @var string|UnitEnum|null $group */
        $group = config('filament-activitylog.navigation.group');

        return $group;
    }

    public static function getNavigationSort(): ?int
    {
        /** @var int|null $sort */
        $sort = config('filament-activitylog.navigation.sort');

        return $sort;
    }

    /**
     * Without these two the navigation item, the breadcrumb and the list heading are named
     * from the class while every other string in the resource is translated, which reads as
     * one screen in two languages.
     */
    public static function getModelLabel(): string
    {
        return __('filament-activitylog::ui.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-activitylog::ui.models');
    }

    /**
     * Filament asks this to render the create button, and under `->strictAuthorization()`
     * asking the Gate throws when the policy declares no `create()`.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Eager loaded here and not in the table because both screens render the morphs: under
     * `Model::preventLazyLoading()` the detail page throws when it asks for the subject.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['subject', 'causer']);
    }

    /**
     * `$recordTitleAttribute` reads the raw column and knows nothing about the translation,
     * so without this the breadcrumb and the page under it disagree.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Activity ? StoredDescription::of($record) : parent::getRecordTitle($record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    /**
     * List and detail only: an entry editable by hand is no audit trail. Rows leave by
     * retention, through `activitylog:clean`.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
