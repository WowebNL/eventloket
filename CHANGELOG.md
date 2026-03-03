# Changelog

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
