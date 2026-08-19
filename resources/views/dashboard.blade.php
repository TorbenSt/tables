<x-layouts.app title="Dashboard">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
        <p class="mt-1 text-stone-600">Zwei getrennte PoC-Module für Gastro-Tischplanung.</p>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-amber-700">Model 1</p>
            <h2 class="mt-1 text-xl font-semibold">Reservierungsplanung</h2>
            <p class="mt-2 text-sm text-stone-600">
                Umfrage zu 10 Wetterszenarien → Freigabeprozente für Innen / Außen Sonne / Außen Schatten,
                bis 14 Tage voraus mit Open-Meteo und nachvollziehbarer Entscheidungstimeline.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('survey') }}" class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700">Umfrage starten</a>
                <a href="{{ route('survey.results') }}" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50">Auswertung</a>
                @if($venue)
                    <a href="{{ route('venues.decisions', $venue) }}" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50">Freigaben</a>
                @endif
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg bg-stone-50 p-3">
                    <dt class="text-stone-500">Umfrage-Antworten</dt>
                    <dd class="text-lg font-semibold">{{ $surveyCount }}</dd>
                </div>
                <div class="rounded-lg bg-stone-50 p-3">
                    <dt class="text-stone-500">Decision Runs</dt>
                    <dd class="text-lg font-semibold">{{ $decisionRuns }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-sky-700">Model 2</p>
            <h2 class="mt-1 text-xl font-semibold">Tisch-Sonnenerkennung</h2>
            <p class="mt-2 text-sm text-stone-600">
                Mindestens 3 Fotos vom gleichen Tag mit Uhrzeit, GPS und Blickrichtung.
                Grok Vision markiert Außentische; Sonnenstand-Heuristik prognostiziert Sonne/Schatten über das Jahr.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('photo-sessions.create') }}" class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700">Fotos hochladen</a>
                <a href="{{ route('photo-sessions.index') }}" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50">Sessions</a>
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg bg-stone-50 p-3">
                    <dt class="text-stone-500">Foto-Sessions</dt>
                    <dd class="text-lg font-semibold">{{ $photoSessions }}</dd>
                </div>
                <div class="rounded-lg bg-stone-50 p-3">
                    <dt class="text-stone-500">Erkannte Tische</dt>
                    <dd class="text-lg font-semibold">{{ $detectedTables }}</dd>
                </div>
            </dl>
        </section>
    </div>

    @if($venue)
        <section class="mt-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Demo-Venue</h2>
            <p class="mt-1 text-sm text-stone-600">{{ $venue->name }} — {{ $venue->latitude }}, {{ $venue->longitude }}</p>
            <p class="mt-2 text-sm">
                Kapazität: {{ $venue->tables_indoor }} innen,
                {{ $venue->tables_outdoor_sun }} Außen Sonne,
                {{ $venue->tables_outdoor_shade }} Außen Schatten
            </p>
        </section>
    @endif
</x-layouts.app>
