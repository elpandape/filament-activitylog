@php
    $activity = $getRecord();

    $log = $activity->log_name;
    $ip = data_get($activity->properties, 'ip');
    $via = data_get($activity->properties, 'via');
    $agent = data_get($activity->properties, 'agent');

    $ip = is_scalar($ip) ? (string) $ip : null;
    $via = is_scalar($via) ? (string) $via : null;
    $agent = is_scalar($agent) ? (string) $agent : null;

    // A scheduled command has no machine behind it: the whole group then reports itself
    // absent instead of leaving two blanks.
    $hasOrigin = $ip !== null || $agent !== null;

    $rotulo = 'display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.5rem;';
    $palabra = 'font-size: 0.625rem; font-weight: 600; letter-spacing: 0.09em; text-transform: uppercase; color: var(--gray-500);';
    $filete = 'flex: 1; height: 1px; background-color: var(--gray-200);';
    $mono = 'font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;';
    $oculto = 'position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip-path: inset(50%); white-space: nowrap;';
@endphp

{{--
    Two meaningful groups rather than four fields: where it came from — the machine, agent
    above its IP — and where it came in through — the channel and the path. An entry with no
    request loses the first group whole, which reads as a deliberate absence.

    Colour and geometry live in inline styles reading the palette variables: the package
    ships no stylesheet, and the `fi-color-*` classes lose the cascade.
--}}
<div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.8125rem; line-height: 1.4;">
    <div>
        <div style="{{ $rotulo }}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var({{ $hasOrigin ? '--gray-400' : '--gray-300' }})" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="flex: none; display: block;" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8"/><path d="M12 3c2.5 2.6 2.5 15.4 0 18-2.5-2.6-2.5-15.4 0-18Z"/></svg>
            <span style="{{ $palabra }}">{{ __('filament-activitylog::ui.detail.origin') }}</span>
            <span style="{{ $filete }}"></span>
        </div>

        @if ($hasOrigin)
            <div style="display: flex; align-items: center; gap: 0.625rem; padding: 0.625rem 0.75rem; border: 1px solid var(--gray-200); border-left: 2px solid var(--info-500); border-radius: 0.625rem; background-color: color-mix(in oklab, var(--gray-500) 5%, transparent);">
                <span style="flex: none; display: flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border: 1px solid var(--gray-200); border-radius: 0.5625rem; background-color: color-mix(in oklab, var(--gray-500) 8%, transparent);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display: block;">
                        <rect x="2.75" y="4.75" width="18.5" height="12.5" rx="2" fill="color-mix(in oklab, var(--info-500) 14%, transparent)" stroke="var(--gray-400)" stroke-width="1.4"/>
                        <path d="M12 17.25v3.25M8.5 20.5h7" stroke="var(--gray-400)" stroke-width="1.4" stroke-linecap="round"/>
                    </svg>
                </span>

                <span style="flex: 1 1 auto; min-width: 0;">
                    @if ($agent !== null)
                        <span style="display: block; font-weight: 600; overflow-wrap: anywhere;">{{ $agent }}</span>
                    @endif

                    @if ($ip !== null)
                        <span style="display: block; margin-top: 0.1875rem; {{ $mono }} font-size: 0.71875rem; color: var(--gray-500); overflow-wrap: anywhere;"><span style="{{ $oculto }}">{{ __('filament-activitylog::ui.detail.ip') }}: </span>{{ $ip }}</span>
                    @endif
                </span>
            </div>
        @else
            <div style="padding: 0.5625rem 0.6875rem; border: 1px dashed var(--gray-300); border-radius: 0.5rem; font-size: 0.6875rem; line-height: 1.5; color: var(--gray-500);">{{ __('filament-activitylog::ui.detail.no_request') }}</div>
        @endif
    </div>

    <div>
        <div style="{{ $rotulo }}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="flex: none; display: block;" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
            <span style="{{ $palabra }}">{{ __('filament-activitylog::ui.detail.entry_point') }}</span>
            <span style="{{ $filete }}"></span>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.4375rem;">
            @if (is_string($log) && $log !== '')
                <span style="display: inline-flex; align-items: center; gap: 0.375rem; align-self: flex-start; max-width: 100%; padding: 0.125rem 0.5rem 0.125rem 0.4375rem; border: 1px solid var(--gray-200); border-radius: 9999px; background-color: color-mix(in oklab, var(--gray-500) 8%, transparent);">
                    <span style="flex: none; width: 0.3125rem; height: 0.3125rem; border-radius: 9999px; background-color: var(--info-500);"></span>
                    <span style="font-size: 0.625rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--gray-400);">{{ __('filament-activitylog::ui.columns.log') }}</span>
                    <span style="{{ $mono }} font-size: 0.6875rem; overflow-wrap: anywhere;">{{ $log }}</span>
                </span>
            @endif

            @if ($via !== null)
                {{-- The slashes are dimmed in place, not replaced, so the path still copies
                     out whole. --}}
                <div style="display: flex; gap: 0.375rem; padding-left: 1px;">
                    <span aria-hidden="true" style="flex: none; font-size: 0.6875rem; line-height: 1.5; color: var(--gray-300);">&#8627;</span>
                    <span style="flex: 1 1 auto; min-width: 0; {{ $mono }} font-size: 0.6875rem; line-height: 1.5; color: var(--gray-500); overflow-wrap: anywhere;">
                        <span style="{{ $oculto }}">{{ __('filament-activitylog::ui.detail.via') }}: </span>@foreach (preg_split('#(/)#', $via, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$via] as $trozo)<span @style(['color: var(--gray-300)' => $trozo === '/'])>{{ $trozo }}</span>@endforeach
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
