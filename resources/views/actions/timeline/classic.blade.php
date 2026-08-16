@php
    use Carbon\CarbonInterface;
    use ElPandaPe\FilamentActivitylog\Support\RecordUrl;
    use ElPandaPe\FilamentActivitylog\Support\EventGlyph;
    use ElPandaPe\FilamentActivitylog\Support\Narrative;

    /** @var string $timeFormat */
    $timeFormat = config('filament-activitylog.formats.time', 'H:i');

    /** @var string $dateFormat */
    $dateFormat = config('filament-activitylog.formats.date', 'j M Y');

    $mono = 'font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;';
    $dia = 'display: flex; align-items: center; gap: 0.625rem; padding: 0.375rem 0; font-size: 0.5625rem; font-weight: 700; letter-spacing: 0.11em; text-transform: uppercase; color: var(--gray-400);';
@endphp

{{--
    The record's history as a timeline: thread on the left, one node per entry, newest
    first. The day is labelled only when it changes.

    Colour and geometry live in inline styles reading the palette variables: the package
    ships no stylesheet, and the `fi-color-*` classes lose the cascade.
--}}
@if ($activities->isEmpty())
    <p style="margin: 0; font-size: 0.875rem; line-height: 1.6; color: var(--gray-500);">{{ __('filament-activitylog::ui.actions.empty') }}</p>
@else
    <div data-timeline="classic" style="display: flex; flex-direction: column;">
        @php
            $previousDay = null;
        @endphp

        @foreach ($activities as $activity)
            @php
                $glyph = EventGlyph::of($activity);
                $when = $activity->created_at;
                $day = $when instanceof CarbonInterface ? $when->toDateString() : null;
                $isNewDay = $day !== $previousDay;
                $previousDay = $day;
                $url = RecordUrl::for($activity);
            @endphp

            @if ($isNewDay && $when instanceof CarbonInterface)
                <div style="{{ $dia }}">
                    <span>{{ $when->translatedFormat($dateFormat) }}</span>
                    <span style="flex: 1; height: 1px; background-color: var(--gray-200);"></span>
                </div>
            @endif

            <div style="display: flex; gap: 0.75rem; min-width: 0;">
                <div style="flex: none; display: flex; flex-direction: column; align-items: center; width: 1.75rem;">
                    <span style="display: flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 9999px; background-color: color-mix(in oklab, var(--{{ $glyph->color }}-500) 14%, transparent); color: var(--{{ $glyph->color }}-600);">
                        <x-filament::icon :icon="$glyph->icon" style="width: 0.875rem; height: 0.875rem; color: inherit;" />
                    </span>

                    {{-- No thread under the last node: it would hang as a loose end. --}}
                    @unless ($loop->last)
                        <span style="flex: 1; width: 1px; min-height: 0.75rem; margin: 0.25rem 0; background-color: var(--gray-200);"></span>
                    @endunless
                </div>

                <div style="flex: 1 1 auto; min-width: 0; padding-bottom: {{ $loop->last ? '0' : '0.875rem' }};">
                    <div style="display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem;">
                        @if ($when instanceof CarbonInterface)
                            <span style="{{ $mono }} font-size: 0.75rem; font-weight: 600; line-height: 1.4;">{{ $when->translatedFormat($timeFormat) }}</span>
                            <span style="{{ $mono }} font-size: 0.6875rem; line-height: 1.4; color: var(--gray-400);">{{ $when->diffForHumans(short: true) }}</span>
                        @endif

                        {{-- Linked only where there is somewhere to go: the resource may not
                             be registered on this panel, and the reader may not be allowed
                             to open it. The number is worth showing either way. --}}
                        @if ($url === null)
                            <span style="{{ $mono }} margin-left: auto; font-size: 0.6875rem; line-height: 1.4; color: var(--gray-400);">#{{ $activity->id }}</span>
                        @else
                            <a href="{{ $url }}" style="{{ $mono }} margin-left: auto; font-size: 0.6875rem; line-height: 1.4; color: var(--primary-600);">#{{ $activity->id }}</a>
                        @endif
                    </div>

                    {{-- The sentence the listing composes, so two screens cannot tell the
                         same fact differently. No links: this record is already on screen. --}}
                    <p style="margin: 0.1875rem 0 0; font-size: 0.875rem; line-height: 1.5; overflow-wrap: anywhere;">{{ Narrative::sentence($activity) }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if ($total > $limit)
        <p style="margin: 1rem 0 0; padding-top: 0.75rem; border-top: 1px solid var(--gray-200); font-size: 0.75rem; line-height: 1.5; color: var(--gray-500);">
            {{ __('filament-activitylog::ui.actions.truncated', ['count' => $limit, 'total' => $total]) }}
        </p>
    @endif
@endif
