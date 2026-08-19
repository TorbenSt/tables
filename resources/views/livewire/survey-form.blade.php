<div>
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Umfrage: Tischfreigaben nach Wetter</h1>
            <p class="mt-1 text-sm text-stone-600">
                Für jedes Szenario: wieviel Prozent der Plätze (Außen Sonne / Außen Schatten / Innen) zur Reservierung freigeben?
            </p>
        </div>
        <p class="text-sm text-stone-500">Schritt {{ $step }} / 10</p>
    </div>

    <div class="mb-6 h-2 overflow-hidden rounded-full bg-stone-200">
        <div class="h-full bg-amber-600 transition-all" style="width: {{ ($step / 10) * 100 }}%"></div>
    </div>

    @if ($step === 0)
        <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Wer füllt aus? (optional)</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-stone-600">Name</span>
                    <input type="text" wire:model="respondent_name" class="mt-1 w-full rounded-md border-stone-300 shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-stone-600">Rolle</span>
                    <input type="text" wire:model="respondent_role" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" placeholder="z. B. Inhaber">
                </label>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" wire:click="next" class="rounded-md bg-stone-900 px-4 py-2 text-sm text-white hover:bg-stone-700">Weiter zu den Szenarien</button>
            </div>
        </div>
    @elseif ($step >= 1 && $step <= 10)
        @php($scenario = $scenarios[$step])
        <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-amber-700">Szenario {{ $step }}</p>
            <h2 class="mt-1 text-xl font-semibold">{{ $scenario['title'] }}</h2>
            <p class="mt-2 text-sm text-stone-600">{{ $scenario['description'] }}</p>

            <div class="mt-6 space-y-6">
                @foreach ([
                    'outdoor_sun' => 'Außen Sonne',
                    'outdoor_shade' => 'Außen Schatten',
                    'indoor' => 'Innen',
                ] as $key => $label)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <label for="ans-{{ $step }}-{{ $key }}">{{ $label }}</label>
                            <span class="font-semibold tabular-nums">{{ $answers[$step][$key] }} %</span>
                        </div>
                        <input
                            id="ans-{{ $step }}-{{ $key }}"
                            type="range"
                            min="0" max="100" step="5"
                            wire:model.live="answers.{{ $step }}.{{ $key }}"
                            class="mt-2 w-full accent-amber-700"
                        >
                        @error("answers.$step.$key") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex justify-between">
                <button type="button" wire:click="back" class="rounded-md border border-stone-300 px-4 py-2 text-sm hover:bg-stone-50">Zurück</button>
                @if ($step < 10)
                    <button type="button" wire:click="next" class="rounded-md bg-stone-900 px-4 py-2 text-sm text-white hover:bg-stone-700">Nächstes Szenario</button>
                @else
                    <button type="button" wire:click="save" class="rounded-md bg-emerald-700 px-4 py-2 text-sm text-white hover:bg-emerald-600">Umfrage speichern</button>
                @endif
            </div>
        </div>
    @endif
</div>
