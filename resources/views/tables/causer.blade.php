@php
    use ElPandaPe\FilamentActivitylog\Support\Narrative;

    $activity = $getRecord();

    $name = Narrative::causerName($activity);
    $role = Narrative::causerRole($activity);
    $initials = $name === null ? null : Narrative::initials($name);
@endphp

@if ($name === null)
    {{-- No causer: it was a command, a job or a deployment. --}}
    <span style="color: var(--gray-500);">{{ __('filament-activitylog::ui.system') }}</span>
@else
    {{-- Colour comes from the panel's palette variables, not from the `fi-color-*` classes:
         those live in `@layer utilities` and lose the cascade against unlayered rules. --}}
    <div style="display: flex; align-items: center; gap: 0.625rem;">
        <span
            title="{{ $name }}"
            style="
                display: flex;
                align-items: center;
                justify-content: center;
                flex: none;
                width: 2rem;
                height: 2rem;
                border-radius: 9999px;
                font-size: 0.6875rem;
                font-weight: 600;
                letter-spacing: 0.02em;
                background-color: color-mix(in oklab, var(--primary-500) 14%, transparent);
                color: var(--primary-600);
            "
        >{{ $initials }}</span>

        <span style="display: flex; flex-direction: column; gap: 0.0625rem; min-width: 0;">
            <span style="font-weight: 600;">{{ $name }}</span>

            @if ($role !== null)
                <span style="font-size: 0.75rem; color: var(--gray-500);">{{ $role }}</span>
            @endif
        </span>
    </div>
@endif
