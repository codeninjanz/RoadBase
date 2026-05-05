# RoadBase

Public web app aggregating NZ road traffic-volume data (AADT) onto a Google Map
for Traffic Management Plan (TMP) authors working under the NZGTTM framework.
Auto-syncs from NZTA, NSLR and council sources; auto-classifies the NZGTTM road
level (LV / 1 / 2 / 3); exports a TMP-ready summary as text or PDF.

Stack: Laravel 11, Inertia.js + React 18, MySQL 8 (with spatial types),
Google Maps JS API, DomPDF.

## Quick start

```bash
cp .env.example .env
php artisan key:generate

# create a MySQL 8 database called `roadbase` and update DB_* in .env

composer install
npm install --legacy-peer-deps
php artisan migrate --seed
npm run dev      # in one terminal
php artisan serve   # in another
```

Open http://localhost:8000.

Set `GOOGLE_MAPS_BROWSER_KEY`, `GOOGLE_MAPS_SERVER_KEY` (for the static map in
PDF exports) and optionally `GOOGLE_PLACES_KEY` in `.env`.

## Syncing data

Phase 1 sources: NZTA TMS, NZTA State Highway AADT, NSLR.

```bash
php artisan sync:run nzta_tms --force
php artisan sync:run nzta_aadt --force
php artisan sync:run nslr --force          # also fires NZGTTM recompute
php artisan nzgttm:recompute               # snap + reclassify on demand
```

Production cadence is wired in `routes/console.php`; run the scheduler with
`php artisan schedule:work`.

## Tests

```bash
php vendor/bin/phpunit
```

## Project layout

- `app/Sync/` — ArcGIS REST client + per-source sync jobs.
- `app/Jobs/RecomputeNzgttmLevels.php` — speed-limit snap + classification.
- `app/Support/Nzgttm.php` — pure classification helper.
- `app/Http/Controllers/` — API + Inertia controllers.
- `resources/js/` — React frontend (Inertia pages + map components).
- `database/migrations/` — schema with spatial columns and indexes.
