@php
    use Carbon\CarbonInterface;
    use ElPandaPe\FilamentActivitylog\Support\EventGlyph;
    use ElPandaPe\FilamentActivitylog\Support\Party;
    use ElPandaPe\FilamentActivitylog\Support\StoredDescription;

    $activity = $getRecord();

    $glyph = EventGlyph::of($activity);

    $traducida = StoredDescription::of($activity);

    $parties = [Party::of($activity, 'causer'), Party::of($activity, 'subject')];

    $when = $activity->created_at;

    /** @var string $format */
    $format = config('filament-activitylog.formats.datetime', 'j M Y H:i:s');

    $rotulo = 'display: block; margin-bottom: 0.375rem; font-size: 0.5625rem; font-weight: 700; letter-spacing: 0.11em; text-transform: uppercase; color: var(--gray-400);';
    $nombre = 'display: block; font-size: 0.875rem; font-weight: 600; line-height: 1.3; overflow-wrap: anywhere;';
    $mono = 'font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;';
@endphp

{{--
    Two halves split by a rule: what happened on the left, who on the right. They share one
    section so nothing stacks — the height is that of the taller half, not the sum of both.
    The causer comes before the subject: that order is what states the direction of the
    action.

    Colour and geometry live in inline styles reading the palette variables: the package
    ships no stylesheet, and the `fi-color-*` classes lose the cascade.
--}}
<div style="display: flex; flex-wrap: wrap; align-items: stretch; gap: 1.5rem;">
    <div style="flex: 3 1 20rem; min-width: 0; display: flex; align-items: stretch; gap: 1.25rem;">
        {{-- Colour and glyph both: the glyph is what separates `created` from `deleted` for
             anyone who cannot tell the green from the red. --}}
        <div style="
            flex: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 5.5rem;
            padding: 1rem 0.5rem;
            border-radius: 0.625rem;
            background-color: color-mix(in oklab, var(--{{ $glyph->color }}-500) 12%, transparent);
            color: var(--{{ $glyph->color }}-600);
        ">
            <x-filament::icon :icon="$glyph->icon" style="width: 1.375rem; height: 1.375rem; color: inherit;" />

            <span style="{{ $mono }} font-size: 0.65rem; line-height: 1.15; letter-spacing: 0.08em; text-transform: uppercase; text-align: center; overflow-wrap: anywhere;">{{ $glyph->label }}</span>
        </div>

        <div style="flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 0.6875rem;">
            {{-- The description, said in the panel's language. No quotes and no monospace:
                 that typography promised "this is literally what the row stores", and once
                 translated it is not. The exact value rides in the `title`. --}}
            <span
                {{-- No `color`, so it inherits the theme's. `--gray-950` does not invert in
                     dark mode and left the line near-black on black. --}}
                style="font-size: 1.0625rem; line-height: 1.5; overflow-wrap: anywhere;"
                @if ($traducida !== $activity->description) title="{{ $activity->description }}" @endif
            >{{ $traducida }}</span>

            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; {{ $mono }} font-size: 0.75rem; line-height: 1.4; color: var(--gray-500);">
                <span style="padding: 0.0625rem 0.375rem; border: 1px solid var(--gray-200); border-radius: 0.3125rem;">#{{ $activity->id }}</span>

                @if ($when instanceof CarbonInterface)
                    <span aria-hidden="true" style="color: var(--gray-300);">·</span>
                    <span>{{ $when->translatedFormat($format) }}</span>
                    <span style="color: var(--gray-400);">({{ $when->diffForHumans() }})</span>
                @endif
            </div>
        </div>
    </div>

    <div style="flex: 2 1 15rem; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 0.875rem; padding-left: 1.5rem; border-left: 1px solid var(--gray-200);">
        @foreach ($parties as $party)
            <div>
                <span style="{{ $rotulo }}">{{ $party->isSubject ? __('filament-activitylog::ui.detail.subject') : __('filament-activitylog::ui.detail.causer') }}</span>

                <div style="display: flex; align-items: center; gap: 0.625rem; min-width: 0;">
                    @if (! $party->exists())
                        <span style="flex: none; display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border: 1px dashed var(--gray-300); border-radius: 9999px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3M13 15h4"/></svg>
                        </span>

                        <span style="min-width: 0;">
                            <span style="{{ $nombre }} color: var(--gray-500);">{{ __('filament-activitylog::ui.detail.system') }}</span>
                            <span style="display: block; font-size: 0.75rem; line-height: 1.4; color: var(--gray-500);">{{ __('filament-activitylog::ui.detail.system_note') }}</span>
                        </span>
                    @else
                        <span style="flex: none; display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: {{ $party->isSubject ? '0.5rem' : '9999px' }}; background-color: color-mix(in oklab, var(--{{ $party->color }}-500) 16%, transparent); color: var(--{{ $party->color }}-600); font-size: 0.6875rem; font-weight: 700;">
                            @if ($party->isSubject)
                                <x-filament::icon :icon="$party->icon" style="width: 1rem; height: 1rem; color: inherit;" />
                            @else
                                {{ $party->initials() }}
                            @endif
                        </span>

                        <span style="min-width: 0;">
                            @if ($party->url === null)
                                <span style="{{ $nombre }}">{{ $party->name }}</span>
                            @else
                                <a href="{{ $party->url }}" style="{{ $nombre }} color: var(--primary-600);">{{ $party->name }}</a>
                            @endif

                            {{-- The role acted with, not today's: the application seals it
                                 onto the row. A subject has no role, so its class goes
                                 there instead. --}}
                            <span style="display: block; font-size: 0.75rem; line-height: 1.4; color: var(--gray-500); overflow-wrap: anywhere;">{{ $party->role ?? $party->type }} <span style="{{ $mono }} font-size: 0.6875rem;">#{{ $party->key }}</span></span>
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
