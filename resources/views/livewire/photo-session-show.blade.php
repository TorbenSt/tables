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
        @foreach ($session->photos as $photo)
            <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm" wire:key="photo-{{ $photo->id }}">
                <div class="mb-2 flex flex-wrap justify-between gap-2 text-xs text-stone-500">
                    <span>{{ \Illuminate\Support\Str::of($photo->captured_at)->substr(0, 5) }} Uhr · Blick {{ $photo->bearing }}°</span>
                    <span>{{ $photo->latitude }}, {{ $photo->longitude }}</span>
                </div>
                <div class="relative overflow-hidden rounded-lg bg-stone-100">
                    <img src="{{ $photo->url() }}" alt="Tischfoto" class="block w-full">
                    @foreach ($session->detectedTables->where('table_photo_id', $photo->id) as $dt)
                        <button
                            type="button"
                            wire:click="selectTable({{ $dt->id }})"
                            class="absolute border-2 {{ $selectedTableId === $dt->id ? 'border-amber-500 bg-amber-400/20' : 'border-sky-500 bg-sky-400/15' }}"
                            style="left: {{ $dt->bbox_x }}%; top: {{ $dt->bbox_y }}%; width: {{ $dt->bbox_w }}%; height: {{ $dt->bbox_h }}%;"
                            title="{{ $dt->label }}"
                        >
                            <span class="absolute -top-5 left-0 whitespace-nowrap rounded bg-stone-900/80 px-1 text-[10px] text-white">{{ $dt->label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @if ($session->detectedTables->isNotEmpty())
        <section class="mt-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Sonn / Schatten Prognose</h2>
                    <p class="text-sm text-stone-600">Unabhängig vom Wetter – nur Sonnenstand + Beobachtungen / Schirm-Heuristik.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <label class="text-sm">
                        <span class="text-stone-600">Tisch</span>
                        <select wire:model.live="selectedTableId" class="ml-2 rounded-md border-stone-300 text-sm">
                            @foreach ($session->detectedTables as $dt)
                                <option value="{{ $dt->id }}">{{ $dt->label }} @if($dt->has_umbrella)(Schirm)@endif</option>
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
                    Beobachtung: {{ $table->observed_condition ?? '–' }}
                    @if($table->has_umbrella)
                        · Schirm ≈ {{ $table->umbrella_height_m }} m hoch, Radius {{ $table->umbrella_radius_m }} m
                    @endif
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
