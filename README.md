# Firmware Download Symfony App

This repository now contains a Symfony 7 app at the repository root that reimplements the existing firmware download flow and adds a protected admin panel for managing software versions.

## What Is Included

- Customer page: `/carplay/software-download`
- API endpoint: `/api2/carplay/software/version`
- Admin panel: `/admin/software-versions`
- Admin login: `/admin/login`
- Seeded data: `data/softwareversions.json`

The app is preloaded with the existing software version data from the legacy `softwareversions.json` file. The legacy files remain in the repo as reference inputs:

- `ConnectedSiteController.php`
- `software.vue`
- `softwareversions.json`

## System Requirements

- Linux
- PHP 8.2 or newer
- Composer 2
- Node.js 20+ and npm

No database is required. Software versions are stored in `data/softwareversions.json`.

## Install

From the repository root:

```bash
composer install
npm install
npm run build
```

## Run Locally

From the repository root:

```bash
php -S 127.0.0.1:8000 router.php
```

Then open:

- Customer page: `http://127.0.0.1:8000/carplay/software-download`
- Admin login: `http://127.0.0.1:8000/admin/login`
- Admin panel: `http://127.0.0.1:8000/admin/software-versions`

## Frontend

The UI is now implemented with Vue and built with Vite.

- Vue source: `frontend/`
- Built assets: `public/assets/app.js` and `public/assets/app.css`

Useful commands:

```bash
npm run build
```

If you change the Vue code, rebuild before serving the app locally or deploying it.

Default local admin credentials are defined in `.env`:

```text
ADMIN_USERNAME=admin
ADMIN_PASSWORD=change-me
```

Change them before using the app outside local development.

## How The App Stores Data

All software versions are stored in:

```text
data/softwareversions.json
```

This file is already populated with the current legacy entries, so the app works immediately after install.

## Managing Software Versions

Use the admin login at `/admin/login`, then manage records at `/admin/software-versions`.

Available actions:

- Add a new software version
- Edit an existing software version
- Delete a software version
- Filter and search the current dataset in the browser
- Paginate through the dataset in the browser
- Copy a row as JSON for review

Fields:

- `Product name`
  Use the same naming pattern as the legacy data, such as `MMI Prime CIC` or `LCI NBT`.
- `System version`
  The display version stored in the source data.
- `System version alt`
  This is the value used for matching after the API removes a leading `v` from the submitted version.
- `Main link`
  Main firmware folder link.
- `ST link`
  ST-specific download link.
- `GD link`
  GD-specific download link.
- `Mark this version as already up to date`
  Equivalent to `latest: true` in the old JSON file.

Extra validation added in the admin flow:

- duplicate `product name + system version alt` combinations are blocked
- only one `latest` entry per product name is allowed
- URLs are validated
- `system_version` must follow the legacy `v...` format
- `system_version_alt` must be the no-`v` match value
- CIC entries are expected to have ST links
- NBT and EVO entries are expected to have GD links

## Matching Rules Preserved From The Legacy App

The API keeps the current behavior:

- `version` is required
- `hwVersion` is required
- a leading `v` or `V` is removed before matching
- matching is done against `system_version_alt`, case-insensitively
- standard hardware only matches standard entries
- LCI hardware only matches LCI entries
- LCI `CIC`, `NBT`, and `EVO` hardware only match entries of the same subtype
- current response messages are preserved, including `Version is required`, `HW Version is required`, `Your system is upto date!`, and `There was a problem identifying your software. Contact us for help.`

## API Notes

The admin list is now served as a paginated API:

- `GET /api/admin/software-versions?page=1&perPage=10&q=MMI&filter=all`

Supported filters:

- `all`
- `latest`
- `st`
- `gd`
- `lci`

Rate limiting is enabled for the exposed APIs:

- firmware lookup API: 30 requests per minute per IP
- admin login API: 10 requests per 5 minutes per IP
- admin list API: 120 requests per minute per IP
- admin write APIs: 30 requests per minute per IP

When a limit is hit, the app returns HTTP `429` with `Retry-After`, `X-RateLimit-Limit`, and `X-RateLimit-Remaining` headers.

Admin API hardening:

- admin login and admin write requests use a session-backed CSRF token
- admin API errors include a structured `error.code` and `error.message`
- create, update, and delete actions are appended to `var/audit.log`

## Notes

- The app is intentionally file-backed so it runs immediately without a database.
- The customer page and admin panel were made more interactive with live hardware detection, recent checks, search/filter controls, and entry previews.
- Admin auth is session-based and driven by `ADMIN_USERNAME` and `ADMIN_PASSWORD` environment variables.
