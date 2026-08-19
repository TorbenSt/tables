<div>
    <div class="mb-6">
        <p class="text-sm"><a href="{{ route('photo-sessions.create') }}" class="text-amber-800 hover:underline">← Auswahl</a></p>
        <h1 class="mt-2 text-2xl font-semibold">Galerie hochladen</h1>
        <p class="mt-1 text-sm text-stone-600">
            Drei Fotos vom gleichen Tag, unterschiedliche Uhrzeiten. Standort und Blickrichtung einmal für alle setzen.
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

        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Gemeinsamer Standpunkt</h2>
            <p class="mt-1 text-sm text-stone-600">Alle Fotos sollten von hier aus in dieselbe Richtung schauen.</p>

            <fieldset class="mt-4 space-y-2 text-sm">
                <legend class="sr-only">Standort je Foto</legend>
                <label class="flex items-start gap-2">
                    <input type="radio" wire:model.live="viewpointMode" value="shared" class="mt-1">
                    <span>Gleicher Standort und gleiche Blickrichtung für alle Fotos</span>
                </label>
                <label class="flex items-start gap-2">
                    <input type="radio" wire:model.live="viewpointMode" value="per_photo" class="mt-1">
                    <span>Standort und Blickrichtung je Foto anpassen</span>
                </label>
            </fieldset>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="block grow text-sm">
                            <span class="text-stone-600">Latitude</span>
                            <input type="text" wire:model="sharedLatitude" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
                        </label>
                        <label class="block grow text-sm">
                            <span class="text-stone-600">Longitude</span>
                            <input type="text" wire:model="sharedLongitude" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
                        </label>
                    </div>
                    <button type="button" data-fill-shared-geo class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50">
                        Aktuellen Standort übernehmen
                    </button>
                </div>
                <x-bearing-compass path="sharedBearing" />
            </div>
        </section>

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
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <span class="text-sm text-stone-600">Bild</span>
                            <label class="mt-2 inline-flex cursor-pointer items-center rounded-md border border-stone-300 bg-white px-3 py-2 text-sm hover:bg-stone-50">
                                Datei wählen
                                <input
                                    type="file"
                                    wire:model="photos.{{ $i }}"
                                    accept="image/*"
                                    data-photo-file
                                    data-photo-index="{{ $i }}"
                                    data-apply-geo="{{ $viewpointMode === 'per_photo' ? '1' : '0' }}"
                                    class="sr-only"
                                >
                            </label>
                            @error("photos.$i") <span class="ml-2 text-xs text-red-600">{{ $message }}</span> @enderror
                            <div wire:loading wire:target="photos.{{ $i }}" class="text-xs text-stone-500">Upload…</div>
                            @if (! empty($m['source']))
                                <p class="mt-2 text-xs text-emerald-800">{{ $m['source'] }}</p>
                            @endif
                            @if (isset($photos[$i]) && is_object($photos[$i]) && method_exists($photos[$i], 'temporaryUrl'))
                                <img src="{{ $photos[$i]->temporaryUrl() }}" alt="Vorschau Foto {{ $i + 1 }}" class="mt-3 max-h-40 rounded-lg border border-stone-200 object-cover">
                            @endif
                        </div>
                        <div class="space-y-4">
                            <label class="block text-sm">
                                <span class="text-stone-600">Uhrzeit</span>
                                <input type="time" wire:model="meta.{{ $i }}.time" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="meta.{{ $i }}.umbrella_hint" class="rounded border-stone-300">
                                Schirm sichtbar (Hinweis)
                            </label>
                        </div>
                    </div>

                    @if ($viewpointMode === 'per_photo')
                        <div class="mt-5 grid gap-4 border-t border-stone-100 pt-5 md:grid-cols-2">
                            <div class="space-y-4">
                                <label class="block text-sm">
                                    <span class="text-stone-600">Latitude</span>
                                    <input type="text" wire:model="meta.{{ $i }}.latitude" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
                                </label>
                                <label class="block text-sm">
                                    <span class="text-stone-600">Longitude</span>
                                    <input type="text" wire:model="meta.{{ $i }}.longitude" class="mt-1 w-full rounded-md border-stone-300 shadow-sm" required>
                                </label>
                            </div>
                            <x-bearing-compass path="meta.{{ $i }}.bearing" />
                        </div>
                    @endif
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
</div>
