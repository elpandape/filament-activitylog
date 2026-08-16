@php
    use ElPandaPe\FilamentActivitylog\Support\Party;

    // Resolved by `Party` and not here: answering the same questions per view is how two
    // screens end up telling the same fact differently, and this view used to skip the
    // validation the detail page does on a configured icon.
    $party = Party::of($getRecord(), 'subject');
@endphp

@if (! $party->exists())
    <span class="fi-ta-placeholder">&mdash;</span>
@else
    {{--
        Colour comes from the panel's palette variables, not from the `fi-color-*` classes:
        those live in `@layer utilities` and lose the cascade against unlayered rules. The
        background is a translucent mix so one value serves both light and dark without a
        stylesheet of our own.
    --}}
    <div style="display: flex; align-items: center; gap: 0.625rem;">
        <span style="
            display: flex;
            align-items: center;
            justify-content: center;
            flex: none;
            width: 2rem;
            height: 2rem;
            border-radius: 0.5rem;
            background-color: color-mix(in oklab, var(--{{ $party->color }}-500) 14%, transparent);
            color: var(--{{ $party->color }}-500);
        ">
            <x-filament::icon :icon="$party->icon" style="width: 1rem; height: 1rem; color: inherit;" />
        </span>

        <span style="display: flex; flex-direction: column; gap: 0.0625rem; min-width: 0;">
            <span style="font-weight: 600;">{{ $party->name }}</span>

            @if ($party->type !== null)
                <span style="font-size: 0.75rem; color: var(--gray-500);">{{ $party->type }}</span>
            @endif
        </span>
    </div>
@endif
