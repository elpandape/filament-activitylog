@php
    use Carbon\CarbonInterface;
    use ElPandaPe\FilamentActivitylog\Support\RecordUrl;
    use ElPandaPe\FilamentActivitylog\Support\EventGlyph;
    use ElPandaPe\FilamentActivitylog\Support\Narrative;

    /** @var string $dateFormat */
    $dateFormat = config('filament-activitylog.formats.date', 'j M Y');

    /** @var string $datetimeFormat */
    $datetimeFormat = config('filament-activitylog.formats.datetime', 'j M Y H:i:s');

    $mono = 'font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;';

    // Filament greys do not invert: `--gray-200` is light in both themes, so a rule painted
    // with it turns near-white on black. A translucent mix over `--gray-500` works in both.
    $line = 'color-mix(in oklab, var(--gray-500) 26%, transparent)';

    $day = 'display: flex; align-items: center; gap: 0.625rem; padding: 0.375rem 0 0.5rem; font-size: 0.5625rem; font-weight: 700; letter-spacing: 0.11em; text-transform: uppercase; color: var(--gray-400);';

    $activitiesByDay = $activities->groupBy(
        static fn ($activity): string => $activity->created_at instanceof CarbonInterface
            ? $activity->created_at->toDateString()
            : '',
    );
@endphp

{{--
    The record's history as a single thread, newest first. Two node weights and no more
    hierarchy: the icon circle marks what opens or closes something, the small ring marks an
    attribute change.

    The thread is drawn as two segments inside each row — one above the node, one below —
    rather than as one absolute line behind them. A line behind would have to be masked at
    each node with the drawer's background colour, which the package does not know: there is
    no stylesheet here and `--gray-*` does not invert.

    Colour and geometry live in inline styles reading the palette variables, for the same
    reason.
--}}
@if ($activities->isEmpty())
    <p style="margin: 0; font-size: 0.875rem; line-height: 1.6; color: var(--gray-500);">{{ __('filament-activitylog::ui.actions.empty') }}</p>
@else
    <div data-timeline="thread">
        @foreach ($activitiesByDay as $entries)
            @php
                $first = $entries->first();
                $when = $first?->created_at;
            @endphp

            @if ($when instanceof CarbonInterface)
                @php
                    // Only these two days get a name; further back the date says more.
                    $label = match (true) {
                        $when->isToday() => __('filament-activitylog::ui.today'),
                        $when->isYesterday() => __('filament-activitylog::ui.yesterday'),
                        default => $when->translatedFormat($dateFormat),
                    };
                @endphp

                <div style="{{ $day }}">
                    <span>{{ $label }}</span>
                    <span style="flex: 1; height: 1px; background-color: {{ $line }};"></span>
                </div>
            @endif

            <div style="display: flex; flex-direction: column; padding-bottom: 0.75rem;">
                @foreach ($entries as $activity)
                    @php
                        $glyph = EventGlyph::of($activity);
                        $moment = $activity->created_at;
                        $url = RecordUrl::for($activity);
                    @endphp

                    <div style="display: flex; gap: 0.75rem; min-width: 0;">
                        <div style="flex: none; display: flex; flex-direction: column; align-items: center; width: 1.875rem;">
                            {{-- The major node is 1.875rem and the minor one 0.6875rem: this
                                 upper segment makes up the difference so both centres land on
                                 the sentence's first line. It is absent on the day's first
                                 entry, where the thread starts at the node. --}}
                            <span style="width: 1px; height: {{ $glyph->minor ? '0.59375rem' : '0' }}; background-color: {{ $loop->first ? 'transparent' : $line }};"></span>

                            @if ($glyph->minor)
                                <span style="flex: none; width: 0.6875rem; height: 0.6875rem; border-radius: 9999px; border: 1.5px solid {{ $line }};"></span>
                            @else
                                <span style="display: flex; align-items: center; justify-content: center; flex: none; width: 1.875rem; height: 1.875rem; border-radius: 9999px; border: 1px solid color-mix(in oklab, var(--{{ $glyph->color }}-500) 45%, transparent); background-color: color-mix(in oklab, var(--{{ $glyph->color }}-500) 12%, transparent); color: var(--{{ $glyph->color }}-600);">
                                    <x-filament::icon :icon="$glyph->icon" style="width: 0.875rem; height: 0.875rem; color: inherit;" />
                                </span>
                            @endif

                            {{-- And the lower segment is absent on the last, or the thread
                                 would hang below the day as a loose end. --}}
                            <span style="flex: 1; width: 1px; min-height: 0.75rem; background-color: {{ $loop->last ? 'transparent' : $line }};"></span>
                        </div>

                        {{-- The sentence the listing composes, so two screens cannot tell the
                             same fact differently. No links: this record is already on screen. --}}
                        <p style="flex: 1 1 auto; min-width: 0; margin: 0.25rem 0 0; padding-bottom: 0.625rem; font-size: 0.875rem; line-height: 1.5; overflow-wrap: anywhere;">{{ Narrative::sentence($activity) }}</p>

                        <div style="flex: none; display: flex; align-items: baseline; gap: 0.375rem; margin: 0.25rem 0 0; padding-left: 0.5rem;">
                            @if ($moment instanceof CarbonInterface)
                                {{-- Distance only; the exact time stays in the `title`. --}}
                                <span title="{{ $moment->translatedFormat($datetimeFormat) }}" style="font-size: 0.75rem; line-height: 1.4; white-space: nowrap; color: var(--gray-500);">{{ $moment->diffForHumans(short: true) }}</span>
                            @endif

                            @if ($url === null)
                                <span style="{{ $mono }} font-size: 0.6875rem; line-height: 1.4; color: var(--gray-400);">#{{ $activity->id }}</span>
                            @else
                                <a href="{{ $url }}" style="{{ $mono }} font-size: 0.6875rem; line-height: 1.4; color: var(--primary-600);">#{{ $activity->id }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    @if ($total > $limit)
        <p style="margin: 0.25rem 0 0; padding-top: 0.75rem; border-top: 1px solid {{ $line }}; font-size: 0.75rem; line-height: 1.5; color: var(--gray-500);">
            {{ __('filament-activitylog::ui.actions.truncated', ['count' => $limit, 'total' => $total]) }}
        </p>
    @endif
@endif
