<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Entscheidungstimeline</h1>
        <p class="mt-1 text-sm text-stone-600">
            Wie sich die Freigabe für ein Zieldatum über mehrere Berechnungsläufe verändert hat.
        </p>
    </div>

    <div class="mb-6 grid gap-4 rounded-xl border border-stone-200 bg-white p-4 shadow-sm md:grid-cols-3">
        <label class="block text-sm">
            <span class="text-stone-600">Zieldatum</span>
            <input type="date" wire:model.live="targetDate" class="mt-1 w-full rounded-md border-stone-300 shadow-sm">
        </label>
        <label class="block text-sm md:col-span-2">
            <span class="text-stone-600">Tischart</span>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($labels as $key => $label)
                    <button
                        type="button"
                        wire:click="$set('category', '{{ $key }}')"
                        class="rounded-md px-3 py-1.5 text-sm {{ $category === $key ? 'bg-stone-900 text-white' : 'border border-stone-300 hover:bg-stone-50' }}"
                    >{{ $label }}</button>
                @endforeach
            </div>
        </label>
    </div>

    @if ($points->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">
            Keine Timeline-Punkte für {{ $targetLabel }}. Zuerst Freigaben berechnen und ggf. mehrmals neu laufen lassen
            (oder historische Runs anlegen).
        </div>
    @else
        @php
            $max = 100;
            $count = max(1, $points->count() - 1);
        @endphp

        <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">{{ $labels[$category] }} für {{ $targetLabel }}</h2>

            <div class="relative mt-8 h-56">
                {{-- Y axis labels --}}
                <div class="absolute inset-y-0 left-0 flex w-10 flex-col justify-between text-xs text-stone-400">
                    <span>100%</span><span>50%</span><span>0%</span>
                </div>

                <div class="absolute inset-0 ml-12 border-l border-b border-stone-200">
                    {{-- grid --}}
                    <div class="absolute inset-x-0 top-0 border-t border-stone-100"></div>
                    <div class="absolute inset-x-0 top-1/2 border-t border-stone-100"></div>

                    {{-- line + points --}}
                    <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none">
                        @if ($points->count() > 1)
                            <polyline
                                fill="none"
                                stroke="#b45309"
                                stroke-width="1.5"
                                vector-effect="non-scaling-stroke"
                                points="@foreach($points as $i => $p){{ ($i / $count) * 100 }},{{ 100 - ($p['percent'] / $max) * 100 }} @endforeach"
                            ></polyline>
                        @endif
                    </svg>

                    @foreach ($points as $i => $p)
                        @php
                            $x = ($points->count() === 1) ? 50 : ($i / $count) * 100;
                            $y = 100 - ($p['percent'] / $max) * 100;
                            $active = ($selected['run_id'] ?? null) === $p['run_id'];
                        @endphp
                        <button
                            type="button"
                            wire:click="selectRun({{ $p['run_id'] }})"
                            class="absolute h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 {{ $active ? 'border-amber-800 bg-amber-500' : 'border-amber-700 bg-white' }}"
                            style="left: {{ $x }}%; top: {{ $y }}%;"
                            title="{{ $p['percent'] }}% am {{ $p['ran_at']->format('d.m.Y H:i') }}"
                        ></button>
                    @endforeach
                </div>
            </div>

            <div class="ml-12 mt-3 flex justify-between text-xs text-stone-500">
                @foreach ($points as $p)
                    <span class="max-w-[6rem] truncate" title="{{ $p['ran_at']->format('d.m.Y H:i') }}">
                        {{ $p['ran_at']->format('d.m. H:i') }}
                    </span>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap gap-3 text-sm">
                @foreach ($points as $p)
                    <button
                        type="button"
                        wire:click="selectRun({{ $p['run_id'] }})"
                        class="rounded-md border px-2 py-1 {{ ($selected['run_id'] ?? null) === $p['run_id'] ? 'border-amber-700 bg-amber-50' : 'border-stone-200' }}"
                    >
                        Run #{{ $p['run_id'] }}: <strong>{{ $p['percent'] }}%</strong>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($selected)
            <div class="mt-6 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold">Detail Run #{{ $selected['run_id'] }}</h3>
                <p class="mt-1 text-sm text-stone-500">
                    Berechnet am {{ $selected['ran_at']->format('d.m.Y H:i') }} · Quelle {{ $selected['source'] }}
                    @if($selected['grok_adjusted']) · Grok-angepasst @endif
                </p>
                <p class="mt-4 text-2xl font-semibold tabular-nums">{{ $selected['percent'] }}% {{ $labels[$category] }}</p>
                <p class="mt-2 text-sm">Szenario #{{ $selected['scenario_id'] }} — {{ config('survey_scenarios.'.$selected['scenario_id'].'.title') }}</p>
                <p class="mt-3 text-sm text-stone-700">{{ $selected['reasoning'] }}</p>
                @if (!empty($selected['weather_day']))
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                        <div class="rounded-lg bg-stone-50 p-3">
                            <dt class="text-stone-500">Temp max</dt>
                            <dd class="font-semibold">{{ $selected['weather_day']['temp_max'] ?? '–' }}°C</dd>
                        </div>
                        <div class="rounded-lg bg-stone-50 p-3">
                            <dt class="text-stone-500">Niederschlag</dt>
                            <dd class="font-semibold">{{ $selected['weather_day']['precipitation_sum'] ?? '–' }} mm</dd>
                        </div>
                        <div class="rounded-lg bg-stone-50 p-3">
                            <dt class="text-stone-500">Regen vormittags</dt>
                            <dd class="font-semibold">{{ $selected['weather_day']['morning_rain_mm'] ?? '–' }} mm</dd>
                        </div>
                        <div class="rounded-lg bg-stone-50 p-3">
                            <dt class="text-stone-500">Regen nachmittags</dt>
                            <dd class="font-semibold">{{ $selected['weather_day']['afternoon_rain_mm'] ?? '–' }} mm</dd>
                        </div>
                    </dl>
                @endif
            </div>
        @endif
    @endif
</div>
