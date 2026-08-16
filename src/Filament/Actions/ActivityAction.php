<?php

declare(strict_types=1);

namespace ElPandaPe\FilamentActivitylog\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Exceptions\InvalidConfiguration;
use Spatie\Activitylog\Support\Config;

/**
 * A record's history in a slide-over: attaches wherever a record is in scope — a table row, a
 * view page header — and shows the most recent entries for it first.
 *
 * A glance, not the activity log resource: no filters, no pagination.
 */
class ActivityAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-activitylog::ui.actions.activity'));
        $this->icon(Heroicon::OutlinedClock);
        $this->color('gray');

        $this->slideOver();
        $this->modalWidth(Width::Large);
        $this->modalHeading(fn (): string => __('filament-activitylog::ui.actions.heading', [
            'record' => $this->getRecordTitle(),
        ]));

        $this->modalSubmitAction(false);
        $this->modalCancelAction(fn (Action $action): Action => $action->label(__('filament-activitylog::ui.actions.close')));

        $this->modalContent(function (?Model $record): View {
            $limit = self::limit();

            $timeline = [
                'activities' => ! $record instanceof Model ? new Collection : self::historyOf($record, $limit),
                'total' => ! $record instanceof Model ? 0 : self::countFor($record),
                'limit' => $limit,
            ];

            return match (config('filament-activitylog.timeline.style', 'classic')) {
                'thread' => view('filament-activitylog::actions.timeline.thread', $timeline),
                default => view('filament-activitylog::actions.timeline.classic', $timeline),
            };
        });

        $this->visible(fn (): bool => Gate::allows('viewAny', Config::activityModel()));
    }

    public static function getDefaultName(): ?string
    {
        return 'activity';
    }

    /**
     * @return Collection<int, Model&ActivityContract>
     *
     * @throws InvalidConfiguration
     */
    protected static function historyOf(Model $record, int $limit): Collection
    {
        /** @var Collection<int, Model&ActivityContract> $activities */
        $activities = self::scopedTo($record)
            ->with(['subject', 'causer'])
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return $activities;
    }

    /**
     * @throws InvalidConfiguration
     */
    protected static function countFor(Model $record): int
    {
        return self::scopedTo($record)->count();
    }

    /**
     * @return Builder<Model&ActivityContract>
     *
     * @throws InvalidConfiguration
     */
    protected static function scopedTo(Model $record): Builder
    {
        /** @var Builder<Model&ActivityContract> $query */
        $query = Config::activityModel()::query();

        return $query
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey());
    }

    protected static function limit(): int
    {
        /** @var int $limit */
        $limit = config('filament-activitylog.timeline.limit', 25);

        return $limit;
    }
}
