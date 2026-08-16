@php
    use ElPandaPe\FilamentActivitylog\Support\Narrative;

    $amendments = Narrative::amendments($getRecord());

    // One grid, repeated identically on the header and on every row: that is what keeps the
    // three columns aligned.
    $rejilla = 'display: grid; grid-template-columns: 0.875rem minmax(6rem, 11rem) minmax(0, 1fr) 1.25rem minmax(0, 1fr); column-gap: 0.75rem; align-items: start;';
    $cabecera = 'font-size: 0.625rem; font-weight: 600; letter-spacing: 0.09em; text-transform: uppercase; color: var(--gray-500);';
    $valor = 'display: inline-block; max-width: 100%; padding: 0.125rem 0.375rem; border-radius: 0.3125rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.75rem; line-height: 1.45; overflow-wrap: anywhere;';
    $viejo = $valor.' background-color: color-mix(in oklab, var(--danger-500) 10%, transparent); color: var(--danger-600);';
    $nuevo = $valor.' background-color: color-mix(in oklab, var(--success-500) 13%, transparent); color: var(--success-600); font-weight: 600;';
    $hueco = 'display: inline-block; max-width: 100%; padding: 0.0625rem 0.4375rem; border: 1px dashed var(--gray-300); border-radius: 0.3125rem; font-size: 0.75rem; font-style: italic; line-height: 1.45; color: var(--gray-500);';
    $flecha = 'padding-top: 0.125rem; text-align: center; color: var(--gray-400); font-size: 0.8125rem; line-height: 1.45;';
    $nota = 'font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--gray-500);';
@endphp

@if ($amendments === [])
    <p style="margin: 0; color: var(--gray-500);">{{ __('filament-activitylog::ui.detail.no_changes') }}</p>
@else
    {{--
        One row per field, three columns: field, before, after. The three confusing cases
        get a shape of their own instead of relying on wording: masked, no previous value,
        and value cleared.

        Colour and geometry live in inline styles reading the palette variables: the package
        ships no stylesheet, and the `fi-color-*` classes lose the cascade.
    --}}
    <div role="table" style="font-size: 0.8125rem;">
        <div role="row" style="{{ $rejilla }} padding-bottom: 0.5rem; border-bottom: 1px solid var(--gray-300);">
            <span></span>
            <span role="columnheader" style="{{ $cabecera }}">{{ __('filament-activitylog::ui.detail.attribute') }}</span>
            <span role="columnheader" style="{{ $cabecera }} color: var(--danger-600);">{{ __('filament-activitylog::ui.detail.before') }}</span>
            <span></span>
            <span role="columnheader" style="{{ $cabecera }} color: var(--success-600);">{{ __('filament-activitylog::ui.detail.after') }}</span>
        </div>

        @foreach ($amendments as $index => $amendment)
            @php
                $primero = ! $amendment['masked'] && $amendment['old'] === null && $amendment['new'] !== null;
                $retirado = ! $amendment['masked'] && $amendment['old'] !== null && $amendment['new'] === null;
            @endphp

            <div role="row" style="{{ $rejilla }} padding: 0.4375rem 0; {{ $index === 0 ? '' : 'border-top: 1px solid var(--gray-200);' }}">
                @if ($amendment['masked'])
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--gray-500)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display: block; margin: 0.3125rem auto 0;" aria-hidden="true"><rect x="4" y="10.5" width="16" height="10.5" rx="2"></rect><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"></path></svg>
                @else
                    <span style="display: block; width: 0.375rem; height: 0.375rem; margin: 0.5rem auto 0; border-radius: 50%; background-color: var({{ $primero ? '--success-500' : ($retirado ? '--danger-500' : '--info-500') }});"></span>
                @endif

                <span role="cell" style="padding-top: 0.1875rem; font-weight: 500; overflow-wrap: anywhere;">{{ $amendment['field'] }}</span>

                @if ($amendment['masked'])
                    {{-- The field is named, its value never: only that it changed. --}}
                    <span role="cell" style="grid-column: 3 / -1; display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.5rem; min-width: 0;">
                        <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.0625rem 0.4375rem; border: 1px dashed var(--gray-300); border-radius: 0.3125rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.75rem; line-height: 1.45; letter-spacing: 0.06em; color: var(--gray-500);">••••••••<span aria-hidden="true" style="letter-spacing: 0;">&rarr;</span>••••••••</span>
                        <span style="{{ $nota }}">{{ __('filament-activitylog::ui.detail.hidden') }}</span>
                    </span>
                @else
                    <span role="cell">
                        @if ($amendment['old'] === null)
                            <span style="{{ $hueco }}">&empty; {{ __('filament-activitylog::ui.detail.empty') }}</span>
                        @else
                            <span style="{{ $viejo }}">{{ $amendment['old'] }}</span>
                        @endif
                    </span>

                    <span aria-hidden="true" style="{{ $flecha }}">&rarr;</span>

                    <span role="cell" style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.4375rem; min-width: 0;">
                        @if ($amendment['new'] === null)
                            <span style="{{ $hueco }}">&empty; {{ __('filament-activitylog::ui.detail.empty') }}</span>
                        @else
                            <span style="{{ $nuevo }}">{{ $amendment['new'] }}</span>
                        @endif

                        @if ($primero)
                            <span style="{{ $nota }}">{{ __('filament-activitylog::ui.detail.first_value') }}</span>
                        @elseif ($retirado)
                            <span style="{{ $nota }}">{{ __('filament-activitylog::ui.detail.cleared') }}</span>
                        @endif
                    </span>
                @endif
            </div>
        @endforeach
    </div>
@endif
