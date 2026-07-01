# Changelog

## v1.0.2 - 2026-07-01

### What's Changed

#### 🐛 Bug Fixes

* Allow GPX uploads and harden event-form file fields (#410) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v1.0.1...v1.0.2

## v1.0.0 - 2026-06-23

### Eventloket v1.0.0

First stable release of the 1.0 line. This release replaces the Open Forms based
event application flow with a natively built Filament wizard, upgrades the
framework stack to Laravel 13 / Filament 5, and adds production grade queue
monitoring, error monitoring and security hardening. It consolidates everything
from the `v1.0.0-beta.1` and `v1.0.0-beta.2` pre-releases and contains 233
commits since `v0.6.6`.

> Read the "Deployment guide" section in full before shipping. There are
required environment changes (Redis sessions, Horizon), data migrations, and a
one-time backfill.

#### Highlights

##### Native event application form (the headline change)

The event application form is no longer rendered and evaluated by Open Forms.
It is now a native multi step Filament wizard with all logic in the
application:

* All 146 Open Forms JsonLogic rules were re-implemented as pure PHP
  (`FormDerivedState`, `FormFieldVisibility`, `FormStepApplicability`,
  `FormSystemDerivedState`) and verified for equivalence against the original
  rules.
* Client side reactivity for conditional fields, step applicability
  (melding versus vergunning path), and live label interpolation using
  per municipality variables.
* Draft autosave with support for multiple named concepts per organiser and
  step resume.
* Interactive Leaflet map for drawing event locations and route lines, with
  GeoJSON persistence.
* Rich PDF submission report generated server side (dompdf).
* "Repeat application" prefill: a new application can be pre-filled from a
  previous case.
* Submissions are written directly to OpenZaak / Objects API by the
  application instead of by Open Forms registration backends.

##### Framework and platform upgrades

* Laravel 12 to Laravel 13.
* Filament 4 to Filament 5.
* Livewire 4, Pest 4, Tinker 3.
* Map stack moved from forked VCS packages (`webbingbrasil/filament-maps`,
  custom `filament-map-picker` fork) to stable released versions
  (`dotswan/filament-map-picker ^2.3`). The private VCS repository entries were
  removed from `composer.json`.

##### Queue monitoring with Laravel Horizon

* Added `laravel/horizon`. Dashboard at `/admin/queue-monitor` (configurable
  via `HORIZON_PATH`), restricted to `AdminUser` only, enforced in all
  environments.
* Two supervisors: `high` and `default` queues on the Redis connection.
* A scheduled `horizon:snapshot` runs every five minutes so the dashboard
  throughput and wait time charts populate.

##### Error monitoring

* Added `sentry/sentry-laravel`, wired into the exception handler.

##### Other notable changes

* Document download fixes for non ASCII filenames and missing extensions.
* Advisor notification fixes (no notifications for concept advice requests).
* Removed the daily `sync:zaaktypen` scheduled command and the unused MySQL
  service from the Sail compose file.
* Removed form development scaffolding (demo PDF commands).
* Refreshed the Composer and npm lock files within the existing version
  constraints and rebuilt the compiled Filament assets to match.
* Removed the Dependabot version update configuration, since dependency updates
  are handled manually.

#### Deployment guide

Perform these steps in order. Steps 4 and 5 are new compared to the 0.6.x line.

##### 1. Code and dependencies

```
composer install --no-dev --optimize-autoloader
npm ci && npm run build


```
This is a major framework bump (Laravel 13, Filament 5). The private VCS map
repositories were removed, so a clean `composer install` is recommended over an
incremental update.

##### 2. Environment variables

Optional but recommended:

| Variable | Purpose |
|---|---|
| `SENTRY_LARAVEL_DSN` | Enables Sentry. Empty disables it. |
| `SENTRY_TRACES_SAMPLE_RATE` / `SENTRY_PROFILES_SAMPLE_RATE` | Default 0.1. |
| `SENTRY_RELEASE` / `SENTRY_ENVIRONMENT` | Tie issues to this release. |
| `HORIZON_PATH` | Dashboard path, default `admin/queue-monitor`. |
| `HORIZON_SLACK_WEBHOOK_URL` | Horizon long wait / failure alerts. |
| `OPENZAAK_BRONORGANISATIE_RSIN` | Default `820151130`. Verify it matches production. |

Defaulted, override only if needed:
`LOGIN_MAX_ATTEMPTS`, `LOGIN_DECAY_SECONDS`, `MFA_MAX_ATTEMPTS`,
`MFA_DECAY_SECONDS`, `PASSWORD_RESET_REQUEST_MAX_ATTEMPTS`,
`PASSWORD_RESET_REQUEST_DECAY_SECONDS`, `PASSWORD_RESET_MAX_ATTEMPTS`,
`PASSWORD_RESET_DECAY_SECONDS` (all default 5 attempts / 900 seconds).

Dev / tooling only: `OPEN_FORMS_FORM_SLUG`, `OPEN_FORMS_ADMIN_TOKEN` (used by
the local field map tooling, not needed in production).

##### 3. Database migrations

```
php artisan migrate --force


```
Six new migrations run:

1. `add_form_state_snapshot_to_zaken_table`: adds a nullable `jsonb`
   `form_state_snapshot` column to `zaken`.
2. `drop_formsubmission_sessions_table`: removes the dead Open Forms session
   mapping table.
3. `seed_default_municipality_variables`: data migration. Seeds the template set
   of municipality variables and copies them to existing municipalities that
   have none, so labels with placeholders render immediately.
4. `rework_event_form_drafts_for_multiple_concepts`: drops the unique
   `(user_id, organisation_id)` constraint to allow multiple concepts, adds a
   replacement index and a denormalised `name` column.
5. (the `event_form_drafts` table itself was introduced earlier in this line via
   `create_event_form_drafts_table`).

##### 4. Queue worker: switch to Horizon (breaking operational change)

The queue is now managed by Horizon, not by a bare `queue:work` / `queue:listen`
process.

* Replace the existing queue worker unit (systemd / supervisor) with
  `php artisan horizon`.
* Do not run `queue:work` and Horizon at the same time. Doing so causes double
  processing.
* On every deploy, signal Horizon to gracefully restart so it picks up new code:
  `php artisan horizon:terminate`.
* Make sure the Laravel scheduler (`php artisan schedule:run` via cron, or
  `schedule:work`) is running, otherwise `horizon:snapshot` never fires and the
  dashboard charts stay empty.
* Redis must be reachable for both queues and sessions.

##### 5. One-time data backfill (post deploy)

Old cases created before this Filament flow still hold their submission only in
the Objects API. Convert them to `form_state_snapshot` so prefill (repeat
application), PDF and the summary work for them:

```
# verify a single case first, nothing is written
php artisan eventform:backfill-snapshots-from-objects --dry-run --zaak=<id>

# then run for all eligible cases
php artisan eventform:backfill-snapshots-from-objects


```
The command is idempotent (only touches cases without a snapshot), repeatable,
and performs one external Objects API call per case. It is intentionally a
command and not a migration, so a slow or unreachable Objects API cannot break
the deploy.

##### 6. Caches

```
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize


```
#### Heads-up and breaking notes

* All users are logged out on deploy (session driver change to Redis plus
  encryption), unless you were already on Redis sessions with encryption, which
  is always recommended on production.
* Redis is now a hard requirement (sessions and queue).
* The queue worker process must be replaced by Horizon.

#### Verification after deploy

* `/admin/queue-monitor` loads for an admin and shows the `high` and `default`
  supervisors as active, with throughput and wait time charts filling in after a
  few minutes of scheduler activity.
* A new event application can be submitted end to end and produces a PDF.
* Sentry receives a test event (if configured).
* The backfill dry run output looks correct before the full run.

#### Authors

* Dion Snoeijen (@dionsnoeijen)
* Michel Verhoeven (@Michel-Verhoeven)
* Lorenso D'Agostino (@LorensoD)


---

**Full changelog:** https://github.com/WowebNL/eventloket/compare/v0.6.6...v1.0.0

## v0.6.6 - 2026-06-15

### What's Changed

#### 🐛 Bug Fixes

* Fix documents stored without a file extension (#380) @Michel-Verhoeven
* Stop notifying advisors about concept advice requests (#379) @Michel-Verhoeven

#### Other changes

* Add v0.6.6 release notes (#385) @Michel-Verhoeven
* Update npm dependencies to resolve security advisories (#382) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.6.5...v0.6.6

## v0.6.5 - 2026-06-04

### What's Changed

#### Other changes

* Chore: added temporary information message for upcomming new form (#367) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.6.4...v0.6.5

## v0.6.3 - 2026-06-02

### What's Changed

#### 🐛 Bug Fixes

* Set max file upload to 60mb (#363) @Michel-Verhoeven
* Fix: show thread messages in correct format (#362) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.6.2...v0.6.3

## v0.6.2 - 2026-05-18

### What's Changed

#### Other changes

* Composer and npm deps update 2026 - 7 (#358) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.6.1...v0.6.2

## v0.6.1 - 2026-05-06

### What's Changed

#### 🐛 Bug Fixes

* Fix getting route line for doorkomst zaken (#357) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.6.0...v0.6.1

## v0.6.0 - 2026-04-22

### What's Changed

#### ✨ New feautures

* Implement new ReportQuestion system (#327) @LorensoD

#### 🐛 Bug Fixes

* Updated max file upload from 20mb to 30mb (#355) @Michel-Verhoeven
* Option to create doorkomst zaken for existing zaken (#351) @Michel-Verhoeven
* Removed uniqueness check on coc_number of an organisation (#350) @Michel-Verhoeven

#### Other changes

* Chore: added v0.6.0 release notes (#356) @Michel-Verhoeven
* Npm deps bump (#344) @Michel-Verhoeven
* Composer deps update 2026-5 (#343) @Michel-Verhoeven
* Chore: bump open forms version to 3.3.13 (#342) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.5.1...v0.6.0

## v0.5.1 - 2026-04-08

### What's Changed

#### 🐛 Bug Fixes

* Fix: Advisory workingstock should only contain advicetrheads in an active status (#337) @Michel-Verhoeven
* Fix: Make coc_number input numeric (#332) @LorensoD
* Fix: max 1000 characters for result / besluit toelichting (#339) @Michel-Verhoeven

#### Other changes

* Chore: update npm deps (#338) @Michel-Verhoeven
* Chore: release notes v0.5.1 (#341) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.5.0...v0.5.1

## v0.5.0 - 2026-04-01

### What's Changed

#### ✨ New feautures

* Zaken can be soft deleted (#318) @LorensoD
* Feat: auto creation of cases for route passing through municipalities (#329) @Michel-Verhoeven

#### 🐛 Bug Fixes

* Fix: Automatisch vertrouwelijkheid instellen bij document upload (#311) @LorensoD
* Fix map widget bounds rendering in modals (#312) @LorensoD

#### Other changes

* Chore: added v0.5.0 release notes (#333) @Michel-Verhoeven
* Added v0.4.3 release notes (#323) @Michel-Verhoeven
* Bumped composer and npm deps (#328) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.4.3...v0.5.0

## v0.4.0 - 2026-03-11

### What's Changed

#### ✨ New feautures

* Feat/full architecture docker compose (#302) @Michel-Verhoeven
* Globally configure Filament Table to persist in session (#307) @LorensoD
* Add tabs with badges and smart defaults to advisor advice thread list (#293) @LorensoD
* Makes Advisories soft deleteable (#289) @LorensoD

#### 🐛 Bug Fixes

* Fix filter action badge active calculation (#310) @LorensoD
* Chore: use fixed pint v1.25.0 in GH action (#308) @Michel-Verhoeven
* Fixed calendar search + raw query usage and added release notes (#304) @Michel-Verhoeven
* Fix: reference update + gemeente on calendar table view (#303) @Michel-Verhoeven
* Fix: verberg zaken met ingetrokken/gesloten resultaten uit kalender e… (#296) @LorensoD
* Fix dark mode colors for thread message entry (#292) @LorensoD
* Remove unique check from invite actions (#297) @LorensoD
* Customize email verification message with expiration details (#291) @LorensoD

#### Other changes

* Added v0.3.0 user releasen notes (#298) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.3.3...v0.4.0

## v0.3.3 - 2026-03-04

### What's Changed

#### 🐛 Bug Fixes

* Fix: make custom document upload rule in config cachable (#301) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.3.2...v0.3.3

## v0.3.2 - 2026-03-03

### What's Changed

#### 🐛 Bug Fixes

* Fix: support max 20mb file upload (#300) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.3.1...v0.3.2

## v0.3.1 - 2026-02-26

### What's Changed

#### 🐛 Bug Fixes

* Fix: document upload validationrule (#299) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.3.0...v0.3.1

## v0.3.0 - 2026-02-25

### What's Changed

#### ✨ New feautures

* Feat: delete imported zaken (#277) @Michel-Verhoeven
* Feat: hardened file upload and allow uploading of emails (#264) @Michel-Verhoeven
* Support postbus addresses in organisation registration (#271) @LorensoD
* Support postbus on edit organisation profile page (#290) @LorensoD

#### 🐛 Bug Fixes

* Fix: voeg postbus_address toe als first-class attribuut op Organisation (#295) @Michel-Verhoeven
* Fix: force organisation real email adress (#294) @Michel-Verhoeven
* Fix: geomety zgw normalisation fix to save geometry to zaak (#285) (#283) (#281)@Michel-Verhoeven
* Fix: validation organisation email address (#280) @Michel-Verhoeven
* Fix: typo zaak translations (#279) @Michel-Verhoeven
* Fix Export user relationship caching in queue workers (#270) @LorensoD
* Fix: Prevent duplicate status notifications when status unchanged (#288) @LorensoD
* Fix: Switch activity log configuration to `logFillable` and `logOnlyDirty` (#287) @LorensoD
* Fix: import data structure and notifications (#274) @LorensoD

#### Other changes

* Docs: v0.2.2 release notes (#269) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.2.2...v0.3.0

## v0.2.1 - 2026-01-27

### What's Changed

#### 🔥 Hotfix

* Fix: organiser can add documents to own organisation zaken (#255) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.2.0...v0.2.1

## v0.2.0 - 2026-01-22

### What's Changed

#### 🚨 deployment action

```
php artisan zaak:update-reference-property --property=statustype_url

















```
#### ✨ New feautures

* Adds zaken import for admins (#217) @LorensoD
* Advisories have an option to view all zaken (#232) @LorensoD

#### 🐛 Bug Fixes

* Municipality admin should be able to delete advisory invites (#246) @LorensoD
* Fix status filtering (#245) @LorensoD
* Fix advisory workingstock showing not correct bagde (#245) @LorensoD
* Fix notifications based on status change (#245) @LorensoD
* Fix auto status change for advisory questions (#245) @LorensoD
* Fix municipality admin should be able to delete advisory invites (#246) @LorensoD
* Hide zaak tabs and infolists that need openzaak (#249) @LorensoD
* Add date format parsing to ZaakImporter and corresponding tests (#248) @LorensoD
* Show view zaak button on calendar for advisory with can_view_any_zaak (#247) @LorensoD
* Fix: added ping_threshold for AWS SES production usage (#252) @Michel-Verhoeven
* Fix: map imported zaak to zaaktype and show imported information on c… (#251) @Michel-Verhoeven

#### Other changes

* Chore/release process update (#244) @Michel-Verhoeven

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.1.1...v0.2.0
