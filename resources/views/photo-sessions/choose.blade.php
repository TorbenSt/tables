<x-layouts.app title="Tisch-Fotos">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold">Wie möchtest du die Terrasse erfassen?</h1>
        <p class="mt-2 max-w-2xl text-stone-600">
            Für die Sonn/Schatten-Prognose brauchen wir mindestens drei Fotos vom <strong>gleichen Spot</strong>
            in die <strong>gleiche Blickrichtung</strong>, aber zu <strong>unterschiedlichen Uhrzeiten</strong>
            (z. B. morgens, mittags, nachmittags).
        </p>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <a href="{{ route('photo-sessions.gallery') }}" class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm transition hover:border-stone-400">
            <p class="text-xs font-medium uppercase tracking-wider text-stone-500">Bestehende Fotos</p>
            <h2 class="mt-1 text-xl font-semibold">Galerie hochladen</h2>
            <p class="mt-2 text-sm text-stone-600">
                Drei Bilder vom selben Tag wählen. Standort und Blickrichtung einmal setzen – gilt für alle Fotos.
                Uhrzeiten kommen aus EXIF oder trägst du nach.
            </p>
            <p class="mt-4 text-sm font-medium text-amber-800">Weiter zur Galerie →</p>
        </a>

        <a href="{{ route('photo-sessions.camera') }}" class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm transition hover:border-stone-400">
            <p class="text-xs font-medium uppercase tracking-wider text-sky-700">Vor Ort</p>
            <h2 class="mt-1 text-xl font-semibold">Mit Handy aufnehmen</h2>
            <p class="mt-2 text-sm text-stone-600">
                Standpunkt merken (GPS + Kompass), dann über den Tag hinweg vom gleichen Platz aus fotografieren.
                Zwischendurch schließen und später fortsetzen ist möglich.
            </p>
            <p class="mt-4 text-sm font-medium text-amber-800">Kamera-Session starten →</p>
        </a>
    </div>
</x-layouts.app>
