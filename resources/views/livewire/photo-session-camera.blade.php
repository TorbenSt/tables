<div>
    <div class="mb-6">
        <p class="text-sm"><a href="{{ route('photo-sessions.create') }}" class="text-amber-800 hover:underline">← Auswahl</a></p>
        <h1 class="mt-2 text-2xl font-semibold">Mit Handy aufnehmen</h1>
        <p class="mt-1 text-sm text-stone-600">
            Zuerst den Standpunkt merken. Danach drei Fotos vom gleichen Platz, zeitlich versetzt
            (morgens / mittags / nachmittags). Du kannst die Seite schließen und später weitermachen.
        </p>
    </div>

    @if ($sessionId)
        <p class="mb-4 rounded-md border border-stone-200 bg-white px-3 py-2 text-sm text-stone-600">
            Entwurf gespeichert – Link zum Fortsetzen:
            <a class="text-amber-800 underline" href="{{ route('photo-sessions.camera.continue', $sessionId) }}">diese Session</a>
        </p>
    @endif

    <div class="mb-6 grid gap-4 rounded-xl border border-stone-200 bg-white p-6 shadow-sm md:grid-cols-3">
        <label class="block text-sm">
            <span class="text-stone-600">Titel</span>
            <input type="text" wire:model="title" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" @disabled($viewpointLocked)>
        </label>
        <label class="block text-sm">
            <span class="text-stone-600">Aufnahmedatum</span>
            <input type="date" wire:model="capture_date" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required @disabled($viewpointLocked)>
        </label>
        <label class="block text-sm">
            <span class="text-stone-600">Venue</span>
            <select wire:model="venue_id" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" @disabled($viewpointLocked)>
                <option value="">—</option>
                @foreach ($venues as $v)
                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if (! $viewpointLocked)
        <section class="rounded-xl border border-sky-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-sky-700">Schritt 1</p>
            <h2 class="mt-1 text-lg font-semibold">Standpunkt merken</h2>
            <p class="mt-2 text-sm text-stone-600">
                Stell dich an den Platz, von dem aus du alle drei Fotos machen wirst. Halte das Handy in Blickrichtung der Terrasse.
            </p>

            <div class="mt-5 grid gap-6 md:grid-cols-2">
                <div class="space-y-4">
                    <button type="button" data-lock-device-fix class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700">
                        GPS &amp; Kompass jetzt lesen
                    </button>
                    <p data-lock-status class="text-sm text-stone-500">Noch keine Gerätewerte.</p>
                    <label class="block text-sm">
                        <span class="text-stone-600">Latitude</span>
                        <input type="text" wire:model="latitude" class="mt-1 w-full rounded-md border-stone-300 shadow-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-stone-600">Longitude</span>
                        <input type="text" wire:model="longitude" class="mt-1 w-full rounded-md border-stone-300 shadow-sm">
                    </label>
                </div>
                <x-bearing-compass path="bearing" />
            </div>

            <button type="button" wire:click="lockViewpoint" class="mt-6 rounded-md bg-emerald-700 px-4 py-2 text-sm text-white hover:bg-emerald-600">
                Standpunkt merken und weiter
            </button>
            @error('bearing') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>
    @else
        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-sky-700">Schritt 2</p>
            <h2 class="mt-1 text-lg font-semibold">Fotos über den Tag</h2>
            <p class="mt-2 text-sm text-stone-600">
                Gemerkter Standpunkt: {{ number_format((float) $latitude, 5) }}, {{ number_format((float) $longitude, 5) }}
                · Blick {{ $bearing }}°.
                Am besten drei Aufnahmen mit klarem Zeitabstand.
            </p>

            <ol class="mt-4 space-y-2 text-sm">
                @forelse ($photos as $photo)
                    <li class="flex items-center justify-between gap-3 rounded-lg bg-stone-50 px-3 py-2">
                        <span>
                            {{ $photo->capturedAtHm() }} Uhr
                            · {{ number_format((float) $photo->latitude, 5) }}, {{ number_format((float) $photo->longitude, 5) }}
                            · {{ $photo->bearing }}°
                        </span>
                        <span class="flex items-center gap-3">
                            <img src="{{ $photo->url() }}" alt="" class="h-10 w-14 rounded object-cover">
                            <button type="button" wire:click="removeShot({{ $photo->id }})" class="text-xs text-red-600 hover:underline">Löschen</button>
                        </span>
                    </li>
                @empty
                    <li class="text-stone-500">Noch keine Aufnahme.</li>
                @endforelse
            </ol>
            <p class="mt-3 text-sm font-medium">{{ $photos->count() }} / 3 Fotos</p>

            <fieldset class="mt-5 space-y-2 text-sm">
                <legend class="font-medium text-stone-700">Für die nächste Aufnahme</legend>
                <label class="flex items-start gap-2">
                    <input type="radio" wire:model.live="shotViewpoint" value="keep" class="mt-1">
                    <span>Gleichen Standpunkt und gleiche Blickrichtung verwenden</span>
                </label>
                <label class="flex items-start gap-2">
                    <input type="radio" wire:model.live="shotViewpoint" value="recapture" class="mt-1">
                    <span>Position und Richtung für dieses Foto neu vom Gerät lesen</span>
                </label>
            </fieldset>

            @error('shot') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-5 flex flex-wrap gap-3">
                <button type="button" data-open-camera-session class="rounded-md bg-stone-900 px-4 py-2 text-sm text-white hover:bg-stone-700">
                    Foto aufnehmen
                </button>
                @if ($photos->count() >= 3)
                    <button type="button" wire:click="finish" class="rounded-md bg-emerald-700 px-4 py-2 text-sm text-white hover:bg-emerald-600" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="finish">Fertig – analysieren</span>
                        <span wire:loading wire:target="finish">Analysiere…</span>
                    </button>
                @endif
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" wire:model="syncAnalyze" class="rounded border-stone-300">
                Analyse sofort (sync)
            </label>
        </section>
    @endif

    <div id="tables-camera-overlay" class="fixed inset-0 z-50 hidden bg-black/90 text-white" aria-hidden="true" wire:ignore>
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <p class="text-sm font-medium">Kamera in Blickrichtung der Terrasse halten</p>
                <button type="button" data-camera-close class="rounded-md bg-white/10 px-3 py-1.5 text-sm">Schließen</button>
            </div>
            <video class="min-h-0 w-full flex-1 bg-black object-contain" playsinline autoplay muted></video>
            <div class="space-y-3 px-4 py-4">
                <p data-camera-heading class="text-center text-lg font-semibold tabular-nums">Kompass…</p>
                <p data-camera-error class="hidden rounded-md bg-red-500/20 px-3 py-2 text-sm text-red-100"></p>
                <button type="button" data-camera-shoot class="w-full rounded-full bg-white py-3 text-base font-semibold text-stone-900">
                    Aufnahme
                </button>
                <p class="text-center text-xs text-white/70">HTTPS nötig. iPhone: Bewegung und Standort erlauben.</p>
            </div>
        </div>
    </div>
</div>
