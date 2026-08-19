<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Tisch-Fotos hochladen</h1>
        <p class="mt-1 text-sm text-stone-600">
            Mindestens 3 Fotos vom gleichen Tag, unterschiedliche Uhrzeiten, mit GPS und Blickrichtung.
        </p>
        <p class="mt-2 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950">
            Am Handy: <strong>Kamera</strong> öffnet die Rückkamera in der App. Standort und Blickrichtung (Kompass) werden im Moment der Aufnahme übernommen.
            Aus der Galerie werden GPS/Uhrzeit aus EXIF gelesen, falls vorhanden – die Blickrichtung steckt dort selten drin.
            Kamera-Zugriff braucht HTTPS (oder localhost).
        </p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 rounded-xl border border-stone-200 bg-white p-6 shadow-sm md:grid-cols-3">
            <label class="block text-sm">
                <span class="text-stone-600">Titel</span>
                <input type="text" wire:model="title" class="mt-1 w-full rounded-md border-stone-300 shadow-sm">
            </label>
            <label class="block text-sm">
                <span class="text-stone-600">Aufnahmedatum</span>
                <input type="date" wire:model="capture_date" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
            </label>
            <label class="block text-sm">
                <span class="text-stone-600">Venue</span>
                <select wire:model="venue_id" class="mt-1 w-full rounded-md border-stone-300 shadow-sm">
                    <option value="">—</option>
                    @foreach ($venues as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @error('photos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @error('meta') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="space-y-4">
            @foreach ($meta as $i => $m)
                <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm" wire:key="photo-slot-{{ $i }}">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold">Foto {{ $i + 1 }}</h2>
                        @if (count($meta) > 3)
                            <button type="button" wire:click="removePhotoSlot({{ $i }})" class="text-xs text-red-600 hover:underline">Entfernen</button>
                        @endif
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="md:col-span-2 lg:col-span-3">
                            <span class="text-sm text-stone-600">Bild</span>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <label class="inline-flex cursor-pointer items-center rounded-md border border-stone-300 bg-white px-3 py-2 text-sm hover:bg-stone-50">
                                    Galerie / Datei
                                    <input
                                        type="file"
                                        wire:model="photos.{{ $i }}"
                                        accept="image/*"
                                        data-photo-file
                                        data-photo-index="{{ $i }}"
                                        class="sr-only"
                                    >
                                </label>
                                <button
                                    type="button"
                                    data-open-camera="{{ $i }}"
                                    class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700"
                                >
                                    Handy-Kamera
                                </button>
                            </div>
                            @error("photos.$i") <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                            <div wire:loading wire:target="photos.{{ $i }}" class="text-xs text-stone-500">Upload…</div>
                            @if (! empty($m['source']))
                                <p class="mt-2 text-xs text-emerald-800">Metadaten: {{ $m['source'] }}</p>
                            @endif
                            @if (isset($photos[$i]) && is_object($photos[$i]) && method_exists($photos[$i], 'temporaryUrl'))
                                <img src="{{ $photos[$i]->temporaryUrl() }}" alt="Vorschau Foto {{ $i + 1 }}" class="mt-3 max-h-40 rounded-lg border border-stone-200 object-cover">
                            @endif
                        </div>
                        <label class="block text-sm">
                            <span class="text-stone-600">Uhrzeit</span>
                            <input type="time" wire:model="meta.{{ $i }}.time" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
                        </label>
                        <label class="block text-sm">
                            <span class="text-stone-600">Latitude</span>
                            <input type="text" wire:model="meta.{{ $i }}.latitude" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
                        </label>
                        <label class="block text-sm">
                            <span class="text-stone-600">Longitude</span>
                            <input type="text" wire:model="meta.{{ $i }}.longitude" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
                        </label>
                        <label class="flex items-center gap-2 text-sm md:col-span-2 lg:col-span-3">
                            <input type="checkbox" wire:model="meta.{{ $i }}.umbrella_hint" class="rounded border-stone-300">
                            Schirm sichtbar (Hinweis)
                        </label>
                        <div class="md:col-span-2 lg:col-span-3">
                            <x-bearing-compass :index="$i" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <button type="button" wire:click="addPhotoSlot" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50">+ weiteres Foto</button>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-stone-600">
                    <input type="checkbox" wire:model="syncAnalyze" class="rounded border-stone-300">
                    Analyse sofort (sync)
                </label>
                <button type="submit" class="rounded-md bg-stone-900 px-4 py-2 text-sm text-white hover:bg-stone-700" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Speichern &amp; analysieren</span>
                    <span wire:loading wire:target="save">Verarbeite…</span>
                </button>
            </div>
        </div>
    </form>

    <div id="tables-camera-overlay" class="fixed inset-0 z-50 hidden bg-black/90 text-white" aria-hidden="true" wire:ignore>
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <p class="text-sm font-medium">Terrasse fotografieren – Kamera in Blickrichtung halten</p>
                <button type="button" data-camera-close class="rounded-md bg-white/10 px-3 py-1.5 text-sm">Schließen</button>
            </div>
            <video class="min-h-0 w-full flex-1 bg-black object-contain" playsinline autoplay muted></video>
            <div class="space-y-3 px-4 py-4">
                <p data-camera-heading class="text-center text-lg font-semibold tabular-nums">Kompass…</p>
                <p data-camera-error class="hidden rounded-md bg-red-500/20 px-3 py-2 text-sm text-red-100"></p>
                <button type="button" data-camera-shoot class="w-full rounded-full bg-white py-3 text-base font-semibold text-stone-900">
                    Aufnahme
                </button>
                <p class="text-center text-xs text-white/70">iPhone: Bewegung/Kompass erlauben. Standort für GPS erlauben.</p>
            </div>
        </div>
    </div>
</div>
