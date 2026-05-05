# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Eventloket is a permit management application for events in Veiligheidsregio Zuid-Limburg (Dutch safety region). Built on Common Ground principles, it integrates with ZGW APIs (OpenZaak, Objects API) for case management. Users include event organizers, permit processors (reviewers), OOV staff, and administrators.

## Tech Stack

- **Laravel 12** / PHP 8.4 / Filament 4 (multi-panel) / Livewire 3
- **PostgreSQL 17+ with PostGIS** (spatial queries for municipalities)
- **Redis** (queue + cache) / **Laravel Passport** (OAuth2 API auth)
- **Vite 6** / Tailwind CSS 4 / Pest 3 (testing) / PHPStan level 5 / Laravel Pint

## Development Commands

This project runs in Docker via Laravel Sail.

```bash
# Start all services (server, queue, logs, vite)
./vendor/bin/sail up -d
composer dev                          # or: sail composer dev

# Testing
./vendor/bin/sail artisan test        # Full Pest suite
./vendor/bin/sail artisan test --filter=TestName  # Single test

# Linting & static analysis
./vendor/bin/sail exec laravel.test ./vendor/bin/pint
./vendor/bin/sail exec laravel.test ./vendor/bin/phpstan analyse

# Frontend
./vendor/bin/sail npm run dev         # Vite dev server
./vendor/bin/sail npm run build       # Production build

# Other
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan queue:listen --tries=1
```

Pre-commit hooks are configured via `.githooks/` (set with `git config core.hooksPath .githooks`).

## Architecture

### Multi-Panel RBAC

Four Filament panels, each with its own resources, pages, and auth:

| Panel | URL | Users |
|-------|-----|-------|
| Admin | `/admin` | System administrators |
| Organiser | `/organiser` | Event organizers (via Organisation) |
| Municipality | `/municipality` | Municipal staff / reviewers |
| Advisor | `/advisor` | Advisory organizations |

Panel providers live in `app/Providers/Filament/`. Each panel has a dedicated CSS theme in `resources/css/filament/`.

### User Polymorphism & Roles

A single `users` table with a `Role` enum. Specialized model subclasses (`AdminUser`, `OrganiserUser`, `MunicipalityUser`, `ReviewerUser`, `AdvisorUser`, etc.) use the `ScopesByRole` trait to apply global query scopes. Policies (27 total) authorize per model.

### Zaak (Case) Model

`Zaak` is the central entity representing a permit case. `Event` extends `Zaak` (scoped via `ZaakEventScope`). Cases flow through ZGW-compliant statuses and are synced to OpenZaak via queued jobs.

Key relationships: `Zaak` belongs to `Organisation`, `Municipality`, and `Zaaktype`. Has many `Thread`s (advice + organiser threads) and `Message`s.

### ZGW Integration (OpenZaak / Objects API)

The custom `woweb/openzaak` package handles API communication. Jobs in `app/Jobs/` handle async creation/updates:
- `CreateZaak`, `AddZaakeigenschappenZGW`, `AddGeometryZGW`, `AddBesluitZGW`, etc.
- Value objects in `app/ValueObjects/` model ZGW API responses (`OzZaak`, `OzZaaktype`, `Informatieobject`, `Besluit`, `Rol`)

### Threading System

Polymorphic threads for collaboration between panels:
- `AdviceThread` — between municipality and advisory services
- `OrganiserThread` — between municipality and organizers
- `Message` model with document attachments and unread tracking via `unread_messages` pivot

### Geospatial

`Municipality` stores PostGIS geometry. `Location` model for event locations. Services in `app/Services/` integrate with the Dutch Locatieserver and Kadaster (BRK) for address lookup and cadastral data. Custom `AsGeoJson` cast handles geometry serialization.

### Invitations

Four invite models (`AdminInvite`, `OrganisationInvite`, `MunicipalityInvite`, `AdvisoryInvite`) with the `Expirable` trait. Signed URL routes in `routes/web.php` link to Livewire acceptance components.

### API Endpoints

Passport-protected routes in `routes/api.php`:
- `POST /locationserver/check` — location validation
- `POST /events/check` — event availability
- `GET /formsessions` — Open Forms session retrieval
- `POST /open-notifications/listen` — ZGW webhook listener
- `GET /municipality-variables/{brk_id}` — per-municipality config

Protected by `EnsureClientIsResourceOwner` middleware; `Application` model owns Passport clients.

### Key Artisan Commands

- `SyncZaaktypen` / `SyncDeelzaaktypen` — import case types from ZGW
- `CreateAdminUser` — bootstrap admin account
- `ImportDoorkomstZaaktypen` — special case type routing

## Dutch Terminology

| Term | English |
|------|---------|
| Zaak | Case (permit application) |
| Zaaktype | Case type |
| Evenement | Event |
| Adviesvraag | Advisory request |
| Adviesdienst | Advisory service |
| Behandelaar | Case handler / reviewer |
| Gemeente | Municipality |
| Vergunning | Permit |
| Besluit | Decision |
| Doorkomst | Route passage (parade/march) |
