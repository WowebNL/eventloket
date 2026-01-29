# Changelog

## v0.2.2 - 2026-01-29

### What's Changed

#### 🐛 Bug Fixes

* Never unmount calendar views in calendar widget by @LorensoD in https://github.com/WowebNL/eventloket/pull/257
* Scope list municipality admin user page to tenant by @LorensoD  in https://github.com/WowebNL/eventloket/pull/258
* MunicipalityAdmin shouldn’t receive notifications by @LorensoD in https://github.com/WowebNL/eventloket/pull/260
* Add tests for generating correct URLs in NewZaakDocument notifications by @LorensoD in https://github.com/WowebNL/eventloket/pull/263
* Faq on homepage uses accordion by @Michel-Verhoeven in https://github.com/WowebNL/eventloket/pull/268

**Full Changelog**: https://github.com/WowebNL/eventloket/compare/v0.2.1...v0.2.2

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
