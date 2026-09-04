<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Außentische auf der Karte</h1>
        <p class="mt-1 text-sm text-stone-600">
            Adresse oder Koordinaten suchen, Satellitenbild prüfen, Tische per Klick markieren.
            Sonnenzeiten kommen aus Sonnenstand plus OSM-Gebäuden und Bäumen — nicht aus dem Luftbild allein.
        </p>
    </div>

    <form wire:submit="search" class="mb-4 flex flex-wrap gap-2">
        <input
            type="text"
            wire:model="query"
            placeholder="Adresse oder 52.5200, 13.4050"
            class="min-w-[16rem] flex-1"
        >
        <button type="submit" class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="search">Suchen</span>
            <span wire:loading wire:target="search">Suche…</span>
        </button>
    </form>

    @if ($searchError)
        <p class="mb-4 text-sm text-red-700">{{ $searchError }}</p>
    @endif

    @if ($geoResults !== [])
        <ul class="mb-4 divide-y divide-stone-100 overflow-hidden rounded-lg border border-stone-200 bg-white text-sm">
            @foreach ($geoResults as $i => $hit)
                <li>
                    <button type="button" wire:click="pickResult({{ $i }})" class="w-full px-3 py-2 text-left hover:bg-stone-50">
                        {{ $hit['display_name'] }}
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mb-4 grid gap-3 md:grid-cols-2">
        <label class="text-sm">
            <span class="text-stone-600">Titel</span>
            <input type="text" wire:model="title" class="mt-1 w-full" placeholder="Café Sonnenseite — Terrasse">
        </label>
        <p class="self-end text-sm text-stone-500">
            Zentrum: {{ number_format($latitude, 6) }}, {{ number_format($longitude, 6) }} · Zoom {{ $zoom }}
        </p>
    </div>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-stone-200 shadow-sm">
        <div
            id="table-sun-map-root"
            class="h-[28rem] w-full"
            wire:ignore
            data-lat="{{ $latitude }}"
            data-lng="{{ $longitude }}"
            data-zoom="{{ $zoom }}"
            data-readonly="0"
            data-tile-url="{{ $imagery['url'] }}"
            data-tile-attr="{{ $imagery['attribution'] }}"
            data-max-zoom="{{ $imagery['max_zoom'] }}"
        ></div>
    </div>
    <p class="mt-2 text-xs text-stone-500">Klick auf die Terrasse setzt einen Tischpunkt. Satellitenbild: Esri World Imagery.</p>

    @if ($tables !== [])
        <ul class="mt-4 divide-y divide-stone-100 overflow-hidden rounded-xl border border-stone-200 bg-white text-sm">
            @foreach ($tables as $i => $t)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="pin-{{ $i }}">
                    <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $t['color_hex'] }}"></span>
                    <span class="font-medium">{{ $t['stable_key'] }}</span>
                    <span class="text-stone-500">{{ $t['lat'] }}, {{ $t['lng'] }}</span>
                    <button type="button" wire:click="toggleUmbrella({{ $i }})" class="rounded-md border border-stone-300 px-2 py-1 text-xs hover:bg-stone-50">
                        {{ $t['has_umbrella'] ? 'Schirm an' : 'Ohne Schirm' }}
                    </button>
                    <button type="button" wire:click="removeTable({{ $i }})" class="ml-auto text-xs text-red-700 hover:underline">Entfernen</button>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <button
            type="button"
            wire:click="generateForecast"
            class="rounded-md bg-stone-900 px-4 py-2 text-sm text-white hover:bg-stone-700 disabled:opacity-50"
            @disabled($tables === [])
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="generateForecast">Sonnenzeiten berechnen</span>
            <span wire:loading wire:target="generateForecast">Lade OSM-Gebäude…</span>
        </button>
        <span class="text-sm text-stone-500">{{ count($tables) }} Tisch{{ count($tables) === 1 ? '' : 'e' }} markiert</span>
    </div>
</div>
