<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $site->displayTitle() }}</h1>
            <p class="mt-1 text-sm text-stone-600">
                {{ $site->latitude }}, {{ $site->longitude }}
                · {{ $site->tables->count() }} Tische
                · {{ $site->occluders->count() }} OSM-Hindernisse
                @if($site->error_message)
                    <span class="text-amber-800">— {{ $site->error_message }}</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('map-sites.create') }}" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50">Neuer Standort</a>
            <button type="button" wire:click="recompute" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="recompute">Neu berechnen</span>
                <span wire:loading wire:target="recompute">Berechne…</span>
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-stone-200 shadow-sm">
        <div
            id="table-sun-map-root"
            class="h-[22rem] w-full"
            wire:ignore
            data-lat="{{ $site->latitude }}"
            data-lng="{{ $site->longitude }}"
            data-zoom="{{ $site->zoom }}"
            data-readonly="1"
            data-tables="{{ json_encode($mapTables) }}"
            data-tile-url="{{ $imagery['url'] }}"
            data-tile-attr="{{ $imagery['attribution'] }}"
            data-max-zoom="{{ $imagery['max_zoom'] }}"
        ></div>
    </div>

    @if ($tables->isNotEmpty())
        <section class="mt-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Sonn / Schatten Prognose</h2>
                    <p class="text-sm text-stone-600">
                        Sonnenstand + OSM-Gebäude/Bäume
                        @if ($table && $table->has_umbrella)
                            , Schirm-Heuristik
                        @endif
                        . Ohne Wetter.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <label class="text-sm">
                        <span class="text-stone-600">Tisch</span>
                        <select wire:model.live="selectedTableId" class="ml-2 rounded-md border-stone-300 text-sm">
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
                    · {{ $table->latitude }}, {{ $table->longitude }}
                </div>
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
