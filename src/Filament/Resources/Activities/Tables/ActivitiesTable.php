<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Filament\Resources\Activities\Tables;

use ElPandaPe\FilamentActivitylog\Support\EventGlyph;
use ElPandaPe\FilamentActivitylog\Support\Narrative;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        /** @var array<int, int> $perPage */
        $perPage = config('filament-activitylog.per_page_options', [10, 25, 50]);

        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated($perPage)
            ->defaultGroup(
                Group::make('created_at')
                    ->date()
                    ->titlePrefixedWithLabel(false)
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->latest())
                    ->getTitleFromRecordUsing(
                        fn (Activity $record): HtmlString => new HtmlString(e(self::dayLabel($record))),
                    ),
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('filament-activitylog::ui.columns.time'))
                    ->time(self::format('time'))
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Activity $record): string => ($record->created_at ?? now())->diffForHumans(short: true)),

                TextColumn::make('event')
                    ->label(__('filament-activitylog::ui.columns.event'))
                    ->badge()
                    ->formatStateUsing(fn (Activity $record): string => EventGlyph::of($record)->label)
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('filament-activitylog::ui.columns.description'))
                    ->html()
                    ->wrap()
                    ->state(fn (Activity $record): HtmlString => Narrative::sentence($record))
                    ->searchable(),

                TextColumn::make('log_name')
                    ->label(__('filament-activitylog::ui.columns.log'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                ViewColumn::make('subject_type')
                    ->label(__('filament-activitylog::ui.columns.subject'))
                    ->view('filament-activitylog::tables.record')
                    ->toggleable(isToggledHiddenByDefault: true),

                ViewColumn::make('causer_id')
                    ->label(__('filament-activitylog::ui.columns.causer'))
                    ->view('filament-activitylog::tables.causer')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('filament-activitylog::ui.columns.event'))
                    ->options([
                        'created' => __('filament-activitylog::ui.events.created'),
                        'updated' => __('filament-activitylog::ui.events.updated'),
                        'deleted' => __('filament-activitylog::ui.events.deleted'),
                        'restored' => __('filament-activitylog::ui.events.restored'),
                    ])
                    ->multiple(),

                Filter::make('logged_at')
                    ->label(__('filament-activitylog::ui.detail.logged_at'))
                    ->schema([
                        DatePicker::make('from')->label(__('filament-activitylog::ui.filters.from')),
                        DatePicker::make('until')->label(__('filament-activitylog::ui.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        if (is_string($from) && $from !== '') {
                            $query->whereDate('created_at', '>=', $from);
                        }

                        if (is_string($until) && $until !== '') {
                            $query->whereDate('created_at', '<=', $until);
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * What each day separator says. The date is always included: the header is emitted by
     * comparing this title against the previous row's — not the group key — so two days
     * sharing a label would merge into a single block, which without the year happens every
     * twelve months.
     */
    private static function dayLabel(Activity $record): string
    {
        $loggedAt = $record->created_at ?? now();

        $date = $loggedAt->translatedFormat(self::format('date'));

        return match (true) {
            $loggedAt->isToday() => __('filament-activitylog::ui.today').' · '.$date,
            $loggedAt->isYesterday() => __('filament-activitylog::ui.yesterday').' · '.$date,
            default => $date,
        };
    }

    private static function format(string $key): string
    {
        /** @var string $format */
        $format = config('filament-activitylog.formats.'.$key, 'H:i');

        return $format;
    }
}
