<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $session->title }}</h1>
            <p class="mt-1 text-sm text-stone-600">
                {{ $session->capture_date->format('d.m.Y') }} · Status: {{ $session->status }}
                @if($session->error_message)
                    <span class="text-red-600">— {{ $session->error_message }}</span>
                @endif
            </p>
        </div>
        <button type="button" wire:click="reanalyze" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="reanalyze">Erneut analysieren</span>
            <span wire:loading wire:target="reanalyze">Analysiere…</span>
        </button>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($session->photos as $photoIndex => $photo)
            <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm" wire:key="photo-{{ $photo->id }}">
                <div class="mb-2 flex flex-wrap justify-between gap-2 text-xs text-stone-500">
                    <span>Foto {{ $photoIndex + 1 }} · {{ $photo->capturedAtHm() }} Uhr · Blick {{ $photo->bearing }}°</span>
                    <span>{{ $photo->latitude }}, {{ $photo->longitude }}</span>
                </div>
                <div class="relative overflow-hidden rounded-lg bg-stone-100">
                    <img src="{{ $photo->url() }}" alt="Tischfoto" class="block w-full">
                    @foreach ($tables as $dt)
                        @php($obs = $dt->observationOnPhoto($photo->id))
                        @continue(! $obs)
                        @php($color = $dt->color_hex ?: '#2563eb')
                        <button
                            type="button"
                            wire:click="selectTable({{ $dt->id }})"
                            class="absolute border-2"
                            style="left: {{ $obs->bbox_x }}%; top: {{ $obs->bbox_y }}%; width: {{ $obs->bbox_w }}%; height: {{ $obs->bbox_h }}%; border-color: {{ $color }}; background-color: {{ $color }}{{ $selectedTableId === $dt->id ? '55' : '22' }};"
                            title="{{ $dt->displayLabel() }}"
                            data-stable-key="{{ $dt->stable_key }}"
                            data-color-hex="{{ $color }}"
                        >
                            <span class="absolute -top-5 left-0 whitespace-nowrap rounded px-1 text-[10px] text-white" style="background-color: {{ $color }};">{{ $dt->stable_key ?: $dt->label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @if ($tables->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-3 text-sm text-stone-700" data-table-legend>
            @foreach ($tables as $dt)
                <div class="inline-flex items-center gap-2" wire:key="legend-{{ $dt->id }}">
                    <span class="inline-block h-3 w-3 rounded-sm" style="background-color: {{ $dt->color_hex }};" data-legend-color="{{ $dt->color_hex }}"></span>
                    <span>{{ $dt->displayLabel() }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($tables->isNotEmpty())
        <section class="mt-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Sonn / Schatten Prognose</h2>
                    <p class="text-sm text-stone-600">Unabhängig vom Wetter – nur Sonnenstand + Beobachtungen / Schirm-Heuristik.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <label class="text-sm">
                        <span class="text-stone-600">Tisch</span>
                        <select wire:model.live="selectedTableId" class="ml-2 rounded-md border-stone-300 text-sm" data-table-select>
                            @foreach ($tables as $dt)
                                <option value="{{ $dt->id }}">
                                    {{ $dt->displayLabel() }} @if($dt->has_umbrella)(Schirm)@endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="text-stone-600">Datum</span>
                        <input type="date" wire:model.live="forecastDate" class="ml-2 rounded-md border-stone-300 text-sm">
                    </label>
                </div>
            </div>

            @if ($table)
                <div class="mt-4 text-sm text-stone-600">
                    <span class="mr-2 inline-block h-2.5 w-2.5 rounded-sm align-middle" style="background-color: {{ $table->color_hex }};"></span>
                    {{ $table->displayLabel() }}
                    · Beobachtungen: {{ $table->observations->count() }} Foto(s)
                    @if($table->has_umbrella)
                        · Schirm ≈ {{ $table->umbrella_height_m }} m hoch, Radius {{ $table->umbrella_radius_m }} m
                    @endif
                </div>
                @if (! empty($visibilityNotes))
                    <ul class="mt-2 list-disc pl-5 text-sm text-amber-800">
                        @foreach ($visibilityNotes as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif

            @if ($dayForecast)
                <div class="mt-6">
                    <p class="mb-3 text-sm font-medium">
                        {{ \Carbon\Carbon::parse($forecastDate)->format('d.m.Y') }}:
                        {{ $dayForecast->sun_hours }} h Sonne / {{ $dayForecast->shade_hours }} h Schatten (6–21 Uhr)
                    </p>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($dayForecast->hourly as $slot)
                            <div
                                class="w-12 rounded-md px-1 py-2 text-center text-[10px] {{ $slot['sun'] ? 'bg-amber-200 text-amber-950' : 'bg-slate-200 text-slate-700' }}"
                                title="{{ $slot['reason'] }} · Az {{ $slot['azimuth'] }}° Elev {{ $slot['elevation'] }}°"
                            >
                                <div class="font-semibold">{{ $slot['hour'] }}h</div>
                                <div>{{ $slot['sun'] ? 'Sonne' : 'Schatten' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($yearOverview->isNotEmpty())
                <div class="mt-8">
                    <h3 class="font-medium">Jahresübersicht (je Monatsmitte)</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-stone-500">
                                <tr>
                                    <th class="py-2 pr-4">Monat</th>
                                    <th class="py-2 pr-4">Sonne (h)</th>
                                    <th class="py-2 pr-4">Schatten (h)</th>
                                    <th class="py-2">Verhältnis</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($yearOverview as $f)
                                    @php($total = max(1, $f->sun_hours + $f->shade_hours))
                                    <tr class="border-t border-stone-100">
                                        <td class="py-2 pr-4">{{ $f->forecast_date->translatedFormat('M Y') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ $f->sun_hours }}</td>
                                        <td class="py-2 pr-4 tabular-nums">{{ $f->shade_hours }}</td>
                                        <td class="py-2">
                                            <div class="flex h-2 w-40 overflow-hidden rounded-full bg-slate-200">
                                                <div class="bg-amber-400" style="width: {{ ($f->sun_hours / $total) * 100 }}%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    @endif
</div>
