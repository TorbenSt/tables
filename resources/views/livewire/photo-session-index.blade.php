<div>
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Foto-Sessions</h1>
            <p class="mt-1 text-sm text-stone-600">Model 2 – Außentische aus Fotos erkennen.</p>
        </div>
        <a href="{{ route('photo-sessions.create') }}" class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700">Neue Session</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Titel</th>
                    <th class="px-4 py-3 font-medium">Datum</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Fotos</th>
                    <th class="px-4 py-3 font-medium">Tische</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $s)
                    <tr class="border-b border-stone-100">
                        <td class="px-4 py-3 font-medium">{{ $s->title }}</td>
                        <td class="px-4 py-3">{{ $s->capture_date->format('d.m.Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs
                                {{ $s->status === 'ready' ? 'bg-emerald-100 text-emerald-800' : ($s->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-stone-100 text-stone-700') }}">
                                {{ $s->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $s->photos_count }}</td>
                        <td class="px-4 py-3">{{ $s->detected_tables_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('photo-sessions.show', $s) }}" class="text-amber-800 hover:underline">Öffnen</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-stone-500">Noch keine Sessions.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
