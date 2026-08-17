---
title: Local Development
description: Set up Sunny locally and run its development and quality tools.
order: 1
---

# Local Development

Sunny is a Laravel 13 application built with PHP 8.4, Livewire 4, Flux UI 2,
Tailwind CSS 4, Vite, and Pest 4. The frontend toolchain uses Node.js 22.

## Initial setup

From the repository root, run:

```bash
composer run setup
```

This command installs Composer dependencies, creates `.env` from
`.env.example` when needed, generates the application key, runs migrations,
installs npm dependencies, and builds the frontend assets.

The example environment uses SQLite, so the default setup does not require a
separate database server. Configure `DB_*` variables if you want to use the
PostgreSQL environment used by the deployed application.

## Optional services

The base application works without third-party API keys. Add these variables
when developing the related kiosk features:

| Variable | Used for |
| --- | --- |
| `MAPBOX_API_KEY` | Address autocomplete in kiosk settings. |
| `OPENWEATHER_API_KEY` | Current weather on the kiosk. |

Mail uses the log driver by default. Team invitations are queued, so run a
queue worker when testing invitation delivery with a real mail provider.

## Run the application

```bash
composer run dev
```

The development script runs the PHP server, queue listener, Pail log viewer,
and Vite together. When using Laravel Herd, the site is also available through
its Herd `.test` domain; Vite and the queue listener are still needed while
developing interactive features.

## Quality checks

```bash
composer run test
composer run lint:check
composer run stan
composer run ci:check
```

- `test` clears cached configuration and runs the Pest suite.
- `lint:check` checks Rector and Duster without changing files.
- `stan` runs PHPStan.
- `ci:check` applies the repository's lint fixes and runs the tests, matching
  the continuous-integration workflow.

Use `composer run lint` when you want Rector and Duster to fix the working tree.

Many Livewire page and component tests are colocated beside their Blade and PHP
files under `resources/views`. Conventional feature, unit, and browser tests
live under `tests/`.

## Work on documentation

Documentation pages live under `docs/users` and `docs/developers`. Before
committing a documentation change, run:

```bash
php artisan docs:lint
php artisan docs:check
php artisan laradocs:cache
```

These commands validate front matter and links, then confirm every page can be
parsed and cached by Laradocs.
