@props(['title' => null])
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Tables PoC' }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-stone-900">
                Tables <span class="font-normal text-stone-500">PoC</span>
            </a>
            <nav class="flex flex-wrap gap-1 text-sm">
                <a href="{{ route('dashboard') }}" class="rounded-md px-3 py-1.5 hover:bg-stone-100 {{ request()->routeIs('dashboard') ? 'bg-stone-100 font-medium' : '' }}">Dashboard</a>
                <a href="{{ route('survey') }}" class="rounded-md px-3 py-1.5 hover:bg-stone-100 {{ request()->routeIs('survey*') ? 'bg-stone-100 font-medium' : '' }}">Umfrage</a>
                @php($venue = \App\Models\Venue::query()->first())
                @if($venue)
                    <a href="{{ route('venues.decisions', $venue) }}" class="rounded-md px-3 py-1.5 hover:bg-stone-100 {{ request()->routeIs('venues.decisions') ? 'bg-stone-100 font-medium' : '' }}">Freigaben</a>
                    <a href="{{ route('venues.timeline', $venue) }}" class="rounded-md px-3 py-1.5 hover:bg-stone-100 {{ request()->routeIs('venues.timeline') ? 'bg-stone-100 font-medium' : '' }}">Timeline</a>
                @endif
                <a href="{{ route('map-sites.create') }}" class="rounded-md px-3 py-1.5 hover:bg-stone-100 {{ request()->routeIs('map-sites*') ? 'bg-stone-100 font-medium' : '' }}">Tisch-Sonne</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-10 text-xs text-stone-500">
        PoC: Model 1 (Wetter/Umfrage) und Model 2 (Karte/Sonne) arbeiten getrennt. Wetter: Open-Meteo. Gebäude: OpenStreetMap.
    </footer>

    @livewireScripts
</body>
</html>
