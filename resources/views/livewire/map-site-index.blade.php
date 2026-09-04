<div>
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Kartenstandorte</h1>
            <p class="mt-1 text-sm text-stone-600">Model 2 – Außentische auf dem Satellitenbild markieren.</p>
        </div>
        <a href="{{ route('map-sites.create') }}" class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700">Neuer Standort</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Titel</th>
                    <th class="px-4 py-3 font-medium">Ort</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Tische</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sites as $s)
                    <tr class="border-b border-stone-100">
                        <td class="px-4 py-3 font-medium">{{ $s->displayTitle() }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $s->address ?: ($s->latitude.', '.$s->longitude) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs
                                {{ $s->status === 'ready' ? 'bg-emerald-100 text-emerald-800' : ($s->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-stone-100 text-stone-700') }}">
                                {{ $s->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $s->tables_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('map-sites.show', $s) }}" class="text-amber-800 hover:underline">Öffnen</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-stone-500">Noch keine Standorte. Tische auf der Karte markieren.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
