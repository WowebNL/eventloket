# Changelog

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
