# Tables PoC

Laravel-Proof-of-Concept mit zwei **getrennten** Modulen:

1. **Reservierungsplanung** – Umfrage (10 Wetterszenarien) + Open-Meteo → Freigabeprozente Innen / Außen Sonne / Außen Schatten, inkl. Entscheidungstimeline
2. **Tisch-Sonnenerkennung** – Fotos mit Meta → Grok Vision (oder Fallback) markiert Außentische → Jahres-Sonn/Schatten-Prognose (ohne Wetter)

## Voraussetzungen

- PHP 8.2+
- Composer
- Node.js 20+
- Optional: `XAI_API_KEY` für Grok (Chat + Vision). Ohne Key laufen Regel-Engine bzw. Demo-Fallbacks.

## Setup

```bash
composer install
cp .env.example .env   # falls noch nicht vorhanden
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

Öffne http://127.0.0.1:8000

### xAI / Open-Meteo

In `.env`:

```env
XAI_API_KEY=xai-...
XAI_MODEL=grok-2-latest
XAI_VISION_MODEL=grok-2-vision-latest
OPEN_METEO_URL=https://api.open-meteo.com/v1
OPEN_METEO_TIMEZONE=Europe/Berlin
```

## Demo-Flow

### Model 1 – Reservierung

1. **Umfrage** `/survey` – für jedes der 10 Szenarien Prozente setzen (Außen Sonne / Schatten / Innen)
2. **Auswertung** `/survey/results` – Mittelwerte über alle Antworten
3. **Freigaben** `/venues/1/decisions` – „Jetzt neu berechnen“ (Open-Meteo 14 Tage + Umfrageprofil; optional Grok-Feinabstimmung)
4. **Timeline** `/venues/1/decisions/timeline` – Zieldatum + Tischart wählen; Verlauf der Freigabe-% über mehrere Runs

CLI:

```bash
php artisan decisions:recompute
php artisan decisions:recompute --no-grok
php artisan decisions:recompute --venue=1
```

Täglich geplant: `06:30` via Laravel Scheduler (`php artisan schedule:work` bzw. Cron).

Timeline mit sichtbarer Änderung (Demo):

```bash
php artisan decisions:recompute --no-grok
php artisan decisions:seed-timeline-demo --days-ago=7 --shift=15
```

Dann unter Timeline ein Zieldatum wählen – z. B. Außen Sonne vor einer Woche vs. heute.

### Model 2 – Fotos / Sonne

### Model 2 – Fotos / Sonne

1. **Auswahl** `/photo-sessions/create` – Galerie **oder** Handy-Kamera
2. **Galerie** `/photo-sessions/create/gallery` – Standort + Kompass einmal für alle Fotos; optional je Foto überschreiben. Uhrzeiten aus EXIF oder manuell (müssen sich unterscheiden).
3. **Handy-Kamera** `/photo-sessions/create/camera` – zuerst Standpunkt merken (GPS/Kompass), dann Fotos zeitlich versetzt vom gleichen Spot. Entwurf speichern und später fortsetzen unter `/photo-sessions/{id}/camera`. HTTPS nötig, iOS: Bewegung + Standort.
4. Analyse sync oder Queue; ohne API-Key: Demo-Bounding-Boxes
5. **Detail** `/photo-sessions/{id}` – Overlays, stündliche Sonne/Schatten-Leiste, Jahresübersicht (Monatsmitte)

## Architektur (kurz)

| Bereich | Technik |
|--------|---------|
| UI | Blade + Livewire 4 + Tailwind 4 |
| Wetter | `OpenMeteoClient` |
| KI | `GrokClient` (xAI) |
| Entscheidungen | `ScenarioMatcher` (Regeln) + optional Grok → `decision_runs` / `decision_entries` |
| Sonne | `SunPositionService` + `SunShadePredictor` |

Module teilen nur das `Venue`-Modell; keine fachliche Kopplung.

## PoC-Grenzen

- Keine echte Reservierungsbuchung / Channel-Manager
- Model 1 und Model 2 arbeiten noch nicht zusammen
- Sonn/Schatten ohne 3D-Gebäudegeometrie und ohne Live-Wetter
- Ein Demo-Venue reicht
- Grok-Ausgaben werden geloggt; ohne API greifen Fallbacks

## Wichtige Pfade

- `config/survey_scenarios.php` – die 10 Szenarien
- `app/Services/Decisions/` – Matching & Decision Engine
- `app/Services/Sun/` – Vision-Analyse & Sonnenprognose
- `app/Livewire/` – UI-Komponenten

## Lizenz

**Proprietär – alle Rechte vorbehalten.** Siehe [LICENSE](LICENSE).

Es handelt sich nicht um Open Source. Nutzung, Kopie, Weitergabe und der Aufbau konkurrierender Produkte auf Basis dieses Codes sind ohne schriftliche Erlaubnis nicht gestattet. Drittanbieter-Pakete (Laravel, Livewire, …) behalten ihre eigenen Lizenzen.
