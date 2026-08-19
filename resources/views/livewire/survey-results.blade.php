<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Umfrage-Auswertung</h1>
            <p class="mt-1 text-sm text-stone-600">
                Mittelwerte über {{ $count }} Antwort{{ $count === 1 ? '' : 'en' }}.
                @if($count === 0) (Default-Profil aktiv, bis Antworten vorliegen) @endif
            </p>
        </div>
        <a href="{{ route('survey') }}" class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700">Neue Antwort</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Szenario</th>
                    <th class="px-4 py-3 font-medium">Außen Sonne</th>
                    <th class="px-4 py-3 font-medium">Außen Schatten</th>
                    <th class="px-4 py-3 font-medium">Innen</th>
                    <th class="px-4 py-3 font-medium">n</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($scenarios as $id => $scenario)
                    @php($row = $profile[$id] ?? null)
                    <tr class="border-b border-stone-100">
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $id }}.</span> {{ $scenario['title'] }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['outdoor_sun'] ?? '–' }}%</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['outdoor_shade'] ?? '–' }}%</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['indoor'] ?? '–' }}%</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['n'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
