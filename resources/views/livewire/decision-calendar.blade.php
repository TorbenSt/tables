<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Freigaben – {{ $venue->name }}</h1>
            <p class="mt-1 text-sm text-stone-600">14-Tage-Horizont auf Basis Umfrageprofil + Open-Meteo.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('venues.timeline', $venue) }}" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50">Timeline</a>
            <button
                type="button"
                wire:click="recompute"
                wire:loading.attr="disabled"
                class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="recompute">Jetzt neu berechnen</span>
                <span wire:loading wire:target="recompute">Berechne…</span>
            </button>
        </div>
    </div>

    @if (! $run)
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">
            Noch keine Berechnung. Klicke „Jetzt neu berechnen“.
        </div>
    @else
        <p class="mb-4 text-sm text-stone-500">
            Run #{{ $run->id }} · {{ $run->ran_at->format('d.m.Y H:i') }} · Quelle: {{ $run->source }}
            @if($run->notes) · {{ $run->notes }} @endif
        </p>

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">Datum</th>
                        <th class="px-4 py-3 font-medium">Szenario</th>
                        <th class="px-4 py-3 font-medium">Wetter</th>
                        <th class="px-4 py-3 font-medium">Außen Sonne</th>
                        <th class="px-4 py-3 font-medium">Außen Schatten</th>
                        <th class="px-4 py-3 font-medium">Innen</th>
                        <th class="px-4 py-3 font-medium">≈ Tische</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($run->entries->sortBy('target_date') as $entry)
                        @php($w = $entry->weather_day ?? [])
                        <tr class="border-b border-stone-100 align-top">
                            <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $entry->target_date->translatedFormat('D d.m.Y') }}</td>
                            <td class="px-4 py-3">
                                #{{ $entry->matched_scenario_id }}
                                <span class="block text-xs text-stone-500">{{ config('survey_scenarios.'.$entry->matched_scenario_id.'.title') }}</span>
                                @if($entry->grok_adjusted)
                                    <span class="mt-1 inline-block rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-medium text-violet-800">Grok</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-stone-600">
                                max {{ $w['temp_max'] ?? '–' }}°C<br>
                                {{ $w['precipitation_sum'] ?? '–' }} mm Regen<br>
                                Bewölkung {{ $w['avg_cloudcover'] ?? '–' }}%
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ $entry->outdoor_sun }}%
                                <span class="block text-xs text-stone-500">{{ (int) round($venue->tables_outdoor_sun * $entry->outdoor_sun / 100) }} / {{ $venue->tables_outdoor_sun }}</span>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ $entry->outdoor_shade }}%
                                <span class="block text-xs text-stone-500">{{ (int) round($venue->tables_outdoor_shade * $entry->outdoor_shade / 100) }} / {{ $venue->tables_outdoor_shade }}</span>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ $entry->indoor }}%
                                <span class="block text-xs text-stone-500">{{ (int) round($venue->tables_indoor * $entry->indoor / 100) }} / {{ $venue->tables_indoor }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-stone-500 max-w-xs">{{ \Illuminate\Support\Str::limit($entry->reasoning, 120) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
