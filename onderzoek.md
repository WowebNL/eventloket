# Onderzoek testbevindingen next/v1.2 (test Rob van Nijnanten, gemeente Beekdaelen, 13-07-2026)

Bron: `input/tests/Resultaat test DEEL1 item 1.eml` en `input/tests/testinstructie-eventloket-testomgeving.pdf` (DEEL 1, testitems 1 t/m 3).

## Samenvatting van de testresultaten

| Testitem | Resultaat |
|---|---|
| 1. Adres en locatie in samenvatting en PDF (stap 5, 6, 7) | OK |
| 1. Automatisch aanvullen straat en plaats (stap 3) | **Bevinding A**: werkt niet bij locatie 1, wel bij locatie 2 (zelfde postcode en huisnummer) |
| 2. Adresaanvulling na stoppen met typen | **Bevinding B**: niet bestaand huisnummer geeft toch een straat en plaats. **Bevinding C**: straat en plaats blijven staan bij wijzigen van bestaand naar niet bestaand huisnummer. Verwacht gedrag volgens tester: een melding dat de combinatie niet bestaat, waarbij handmatig invullen mogelijk blijft. |
| 3. Zichtbaarheid knop "Nieuwe versie" | **Bevinding D**: zowel organisator als behandelaar zien de knop bij alle documenten, inclusief het aanvraagformulier (auteur "Eventloket"). Verwacht: alleen eigenaar of dezelfde groep, systeemdocumenten alleen platformbeheerder. |

---

## Bevinding D: "Nieuwe versie" zichtbaar voor iedereen (regressie, hoogste prioriteit)

Let op voor de branchstrategie: dit probleem bestaat in deze vorm **alleen op `next/v1.2`**. Op `main` staat de action zonder override (`NewDocumentVersionAction::make($this->zaak)` zonder extra `->visible()`), daar werkt de autorisatie dus gewoon. Het MessageForm-gat hieronder bestaat wél op beide branches. Zie de sectie "Branch- en releasestrategie" onderaan.

### Oorzaak (bevestigd)

De autorisatie zit correct in `NewDocumentVersionAction::make()`:

```php
// app/Filament/Shared/Resources/Zaken/Actions/NewDocumentVersionAction.php:30
->visible(fn (array $record): bool => DocumentVersionAuthorizer::canAddVersion(auth()->user(), $zaak, $record['uuid']))
```

Maar in de documententabel wordt die visibility daarna **overschreven**:

```php
// app/Livewire/Zaken/ZaakDocumentsTable.php:111-112
NewDocumentVersionAction::make($this->zaak)
    ->visible(fn (): bool => ! $this->submissionOnly),
```

In Filament vervangt een tweede `->visible()` de eerdere closure volledig (het is één property, zie `vendor/filament/actions/src/Concerns/CanBeHidden.php`). De `DocumentVersionAuthorizer`-check wordt dus nooit meer uitgevoerd. Alleen de `->authorize()`-check (mag de gebruiker documenten uploaden op de zaak) blijft over, en die is waar voor organisator én behandelaar. Precies wat de tester ziet.

Tijdlijn van de regressie:

- 15-06 `90e5542a`: autorisatie op de knop toegevoegd (visible met authorizer).
- 29-06 `ea752c64` (One Ground optimalisaties): `->visible(fn () => ! $this->submissionOnly)` toegevoegd in `ZaakDocumentsTable`, waarmee de authorizer-check onbedoeld werd weggegooid.
- 09-07 `9254b944`: authorizer aangescherpt (aanvraagformulier alleen admin), maar de knop gebruikte de authorizer op dat moment al niet meer.

### Tweede gat: MessageForm

In `app/Livewire/Thread/MessageForm.php` (bijlage bij een thread-bericht, optie "Nieuwe versie van bestaand bestand") wordt `NewDocumentVersionAction::createNewDocumentVersion()` direct aangeroepen zonder enige `DocumentVersionAuthorizer`-check. De select toont bovendien alle documenten van de zaak. Elke gebruiker die een bericht mag plaatsen kan zo dus alsnog een nieuwe versie van elk document (ook het aanvraagformulier) uploaden.

### Oplossing

1. `ZaakDocumentsTable.php:112`: vervang `->visible(fn () => ! $this->submissionOnly)` door `->hidden(fn () => $this->submissionOnly)`. `hidden()` is een aparte property die met `visible()` ge-AND wordt, dus de authorizer-check in de action zelf blijft dan werken.
2. `MessageForm.php`: filter de opties van `existing_document` op `DocumentVersionAuthorizer::canAddVersion(...)` en voer dezelfde check server-side uit in de action (defense in depth, de options-filter is alleen UI).
3. Overweeg de check ook in `NewDocumentVersionAction::createNewDocumentVersion()` zelf af te dwingen (throw authorization exception), zodat geen enkele aanroeproute er omheen kan.
4. Regressietests toevoegen:
   - Livewire-test op `ZaakDocumentsTable` die per rol (organisator eigenaar, organisator andere organisatie, behandelaar, admin) asserteert of de action zichtbaar of verborgen is, ook specifiek voor het aanvraagformulier. De bestaande `DocumentVersionAuthorizerTest` test alleen de klasse zelf, daarom is deze regressie niet opgevallen.
   - Test op `MessageForm` dat een niet-gerechtigde gebruiker geen versie kan toevoegen via de bericht-route.

---

## Bevinding B: niet bestaand huisnummer geeft toch straat en plaats (bevestigd met repro)

### Oorzaak (bevestigd)

`LocatieserverService::getBagObjectByPostcodeHuisnummer()` (app/Services/LocatieserverService.php:58) gebruikt de PDOK **free text search** (`q = "{postcode} {huisnummer}"`). Die zoekfunctie is fuzzy: bij een niet bestaand huisnummer geeft PDOK gewoon het best scorende adres in de buurt terug (bijvoorbeeld huisnummer 1 bij dezelfde postcode). De code filtert daarna alleen op huisletter en toevoeging, maar verifieert **nooit** dat de teruggekregen postcode en het huisnummer overeenkomen met de invoer.

Repro (tijdelijke test, inmiddels verwijderd): met een gefakete PDOK-response voor "6361 BZ" huisnummer 1 geeft `getBagObjectByPostcodeHuisnummer('6361 BZ', '999')` het BagObject van huisnummer 1 terug.

### Oplossing

In `getBagObjectByPostcodeHuisnummer()` na het vinden van een kandidaat verifiëren dat het document exact matcht met de invoer:

- postcode genormaliseerd vergelijken (spaties strippen, uppercase, want PDOK geeft "6361BZ" en de gebruiker typt "6361 BZ"),
- huisnummer als string vergelijken (PDOK geeft het soms als integer terug),
- geen match betekent `null` teruggeven.

Impact op andere aanroepers van deze methode (allemaal gewenst gedrag):

- `AddressNL` (formulier): dit is de fix zelf.
- `EventLocationGeometryBuilder` (submit): voorkomt dat een verkeerde geometrie aan de zaak hangt bij een niet bestaand adres.
- `HasOrganisationAddressForm` (organisatieprofiel): zelfde autofill-gedrag, profiteert mee.

Let op: `getBrkIdentificationByPostcodeHuisnummer()` (gemeentebepaling) bewust fuzzy laten. Voor het bepalen van de gemeente is het juist goed dat een postcode zonder exact huisnummer toch een gemeente oplevert.

Tests: unit test op `LocatieserverService` met gefakete PDOK-responses (exacte match, afwijkend huisnummer, afwijkende postcode, spatie- en hoofdlettervarianten). Er bestaat al `tests/Feature/Services/LocatieserverServiceTest.php` om op aan te haken.

---

## Bevinding C: oude straat en plaats blijven staan na wijzigen naar niet bestaand huisnummer

### Oorzaak (bevestigd)

In `AddressNL::lookupCallback()` (app/EventForm/Components/AddressNL.php:132) wordt bij een mislukte lookup vroegtijdig gereturned:

```php
if ($bag === null) {
    return;
}
```

De eerder automatisch ingevulde straat en plaats blijven daardoor staan, terwijl ze bij het nieuwe huisnummer niet meer kloppen. Zodra bevinding B is opgelost komt dit pad vaker voor (elke niet bestaande combinatie levert dan `null` op), dus B en C moeten samen opgepakt worden.

### Oplossing

In `lookupCallback()` bij `$bag === null` (en alleen wanneer postcode en huisnummer wel allebei ingevuld zijn):

1. Straatnaam en woonplaatsnaam leegmaken, zodat er geen stale adres achterblijft.
2. Een Filament-notificatie tonen: "Geen adres gevonden voor deze postcode en huisnummer-combinatie. Controleer de invoer of vul straat en plaats handmatig in." Dit matcht de verwachting van de tester: wél een melding, maar handmatig invullen blijft mogelijk (de velden zijn gewoon bewerkbaar en verplicht).

Randgeval om te accepteren of af te vangen: als de gebruiker na een mislukte lookup straat en plaats handmatig heeft ingevuld en daarna nog een huisletter of toevoeging typt, wordt de lookup opnieuw uitgevoerd en zou de handmatige invoer gewist worden. Voorstel: alleen leegmaken wanneer de huidige waarden gelijk zijn aan de laatst automatisch ingevulde waarden (bijhouden in een verborgen subveld, bijvoorbeeld `_autofilled`), anders alleen de notificatie tonen. Als dat te zwaar voelt kan variant 1 (altijd leegmaken plus notificatie) als eerste stap, het randgeval is klein.

Tests: uitbreiden van `tests/Unit/EventForm/Components/AddressNLTest.php` plus een Livewire-test op `EventFormPage` die het volledige gedrag dekt (vullen, wijzigen naar niet bestaand, velden leeg plus notificatie).

---

## Bevinding A: autofill werkt niet bij locatie 1, wel bij locatie 2

### Onderzoek tot nu toe

Server-side is de keten **aantoonbaar correct**. Met een tijdelijke Livewire-test (inmiddels verwijderd) op `EventFormPage` is geverifieerd dat:

- de eerste repeater-rij (die via `$set('adresVanDeGebouwEn', [uuid => []])` wordt geseed in de CheckboxList `waarVindtHetEvenementPlaats`, LocatieVanHetEvenement2Step.php:67) na het zetten van postcode en huisnummer netjes straat en plaats gevuld krijgt,
- een tweede rij dat ook krijgt.

De fout zit dus in de browser (timing of DOM-morph), niet in de PHP-logica. De meest waarschijnlijke verklaring, met een gedocumenteerd precedent in dezelfde step (zie het commentaar bij `naamVanDeRoute` in LocatieVanHetEvenement2Step.php:153, waar getypte invoer werd "platgewalst" door een re-render):

1. De huisnummer-sync (debounce 750ms) triggert in dezelfde Livewire-request niet alleen de PDOK-autofill, maar via `EventFormPage::updated()` ook de **synchrone gemeentebepaling** (`ServiceFetcher::fetch('inGemeentenResponse')`, met een externe PDOK-call en PostGIS-queries). Die request duurt daardoor seconden.
2. Bij locatie 1 verandert de uitkomst van die gemeentebepaling de pagina (InfoText "U gaat verder met deze aanvraag voor de gemeente ..." verschijnt, eventueel de NotWithin-waarschuwing), dus er volgt een grote DOM-morph. Alles wat de gebruiker in dat venster deed (doorklikken naar het straatveld, beginnen te typen, de plusknop) kan de autofill-respons laten verloren gaan of platwalsen.
3. Bij locatie 2 is de gemeente al bepaald en gecachet (ServiceFetcher cachet op input-hash), de request is snel en er verandert weinig aan de pagina, dus daar gaat het goed. Dat verklaart de asymmetrie tussen rij 1 en rij 2.

### Aanvullende bevestiging tijdens de uitvoering

Twee dingen bevestigen bovenstaande hypothese en bepalen de aanpak:

1. **De race is een bekend, gedocumenteerd patroon in deze codebase.** In `tests/Playwright/scenario-locatie-route-behoudt-velden.spec.mjs` staat een `test.fixme` voor exact dezelfde klasse race (route-tekenen plus tegelijk velden invullen). De teamnotitie daar stelt letterlijk dat de productie-fix (`->live()` op `naamVanDeRoute`) voor échte tikkende gebruikers de race wint, maar dat Playwright's atomic `fill()` de race in extreme timing alsnog kan verliezen, en dat dit **niet betrouwbaar in CI te reproduceren of te fixen is zonder de gemeente-detect-rerender van dotswan te isoleren van onafhankelijke velden**. Notitie eindigt met: "Manual test in browser: typen werkt prima." De autofill van straat/plaats bij locatie 1 is dezelfde race, nu op de PDOK-autofill in plaats van op `naamVanDeRoute`.

2. **De E2E-repro is in deze werkomgeving niet uitvoerbaar.** De Playwright-suite logt in als een geseede organiser (`noah.degraaf@example.net`, zie `auth.setup.mjs`). Die gebruiker bestaat niet in de lokale database en het is een faker-gegenereerd e-mailadres dat in geen enkele seeder voorkomt, dus de suite kan hier niet betrouwbaar draaien. Reproduceren vereist de ingerichte testomgeving.

### Update: bevinding A inmiddels wél in de browser gereproduceerd

Na het herstellen van de Playwright-setup (deterministische organiser via `PlaywrightUserSeeder`, plus de ontbrekende `.live.debounce.750ms`-selector-modifier in `helpers/form-invullen.mjs`) is de race lokaal reproduceerbaar. Met een echt adres (6411CD/32 = Coriovallumstraat, Heerlen):

- rij 1 vult straat/plaats correct aan;
- een tweede toegevoegde rij met hetzelfde adres blijft leeg (`straat=""`, `plaats=""`), terwijl de server-side lookup dit wél bepaalt.

Welke rij het slachtoffer is hangt van de timing af (de tester zag rij 1 falen, deze run rij 2), wat past bij een re-render-race en niet bij een logische fout. Vastgelegd als `test.fixme` in `tests/Playwright/scenario-adres-autofill-en-melding.spec.mjs`. Een fix is nu wél browser-verifieerbaar; de structurele richting (gemeente-detect loskoppelen van de veld-sync) staat hieronder ongewijzigd.

### Noot: de "geen foutmelding bij niet-bestaand adres" (5541WG/99)

Getest voorbeeld: postcode 5541WG huisnummer 99. Dat adres bestaat niet: de echte 5541WG (Zandakker, Reusel) heeft alleen huisnummers 2 t/m 8. PDOK's fuzzy free-search geeft voor "5541WG 99" huisnummer-99-adressen uit heel andere plaatsen terug (Schuurmanstraat/Mimosastraat Zwolle, Jupiterhof Maastricht). De oude code (next/v1.2) pakt daardoor stilzwijgend een verkeerd adres en toont geen melding. Dat is precies bevinding 2. Op de fix-branch returnt `getBagObjectByPostcodeHuisnummer('5541WG','99')` nu `null` (geen exacte postcode+huisnummer-match) en verschijnt de melding "Geen adres gevonden" (browser-geverifieerd). De gebruiker zag geen melding omdat die op next/v1.2 test, waar de fix (PR #445) nog niet in zit; via de beta-merge komt hij daar vanzelf terecht.

### Besluit: bevinding A niet blind meefixen (achterhaald door bovenstaande update)

Omdat (a) de server-side logica aantoonbaar correct is, (b) dit een browser-morph-race is die het team zelf al als niet-CI-reproduceerbaar heeft gemarkeerd, en (c) een betrouwbare repro hier niet mogelijk is, wordt bevinding A **niet** in deze fix-branch/PR meegenomen. Een codewijziging aan de kern-reactiviteit van het formulier zonder browser-verificatie is precies de gok waar het plan voor waarschuwt en die de gemeentebepaling (kritieke functionaliteit) kan breken.

### Vervolg voor bevinding A (apart, browser-geverifieerd)

1. In de ingerichte testomgeving een Playwright-repro schrijven modelleren op `scenario-locatie-route-behoudt-velden.spec.mjs`: locatie 1 postcode plus huisnummer invullen en direct doorklikken terwijl de gemeente-detect-request loopt, en asserteren dat straat/plaats gevuld raken en blijven.
2. Structurele richting (pas ná reproduceerbaar bewijs): de trage gemeentebepaling loskoppelen van de veld-sync zodat de autofill-response snel terugkomt en de morph niet met de autofill botst. Opties, in oplopende ingrijpendheid:
   - de dubbele PDOK-call schrappen (de autofill doet een lookup én `absorbAddress` in de gemeentecheck doet er nog één in dezelfde request),
   - de gemeentebepaling pas uitvoeren wanneer het adres compleet is in plaats van bij elke wijziging onder `adresVanDeGebouwEn`,
   - de gemeentebepaling asynchroon maken (aparte deferred Livewire-update na de veld-sync), zodat de gemeente-detect-rerender geïsoleerd wordt van de adresvelden. Dit is ook de isolatie die de bestaande `test.fixme` noemt als de echte oplossing voor deze raceklasse.

---

## Branch- en releasestrategie

Afspraak: fixes komen op een fix-branch **vanaf `main`**, daar wordt een nieuwe beta van getagd, en die beta moet daarna succesvol in `next/v1.2` gemerged worden.

Vergelijking van de betrokken bestanden tussen `origin/main` en `origin/next/v1.2`:

| Bestand | Status | Relevantie |
|---|---|---|
| `app/EventForm/Components/AddressNL.php` | gelijk | bevinding C, fix op main merget clean |
| `app/Filament/Organiser/Pages/EventFormPage.php` | gelijk | bevinding A, fix op main merget clean |
| `app/Support/Documents/DocumentVersionAuthorizer.php` | gelijk | geen wijziging nodig |
| `app/Services/LocatieserverService.php` | klein verschil (alleen extra `nummeraanduiding_id` in de `fl`-parameter op next/v1.2) | bevinding B, fix op main; eventueel triviaal merge-conflict, beide kanten behouden |
| `app/Livewire/Thread/MessageForm.php` | verschilt (zgw-client refactor op next/v1.2) | MessageForm-gat bestaat op beide branches; fix op main, bij de merge opletten dat de `Zgw::connection`-refactor van next/v1.2 blijft staan |
| `app/Livewire/Zaken/ZaakDocumentsTable.php` | verschilt wezenlijk (`submissionOnly` en de `->visible()`-override bestaan alleen op next/v1.2) | **de tabel-regressie van bevinding D is niet op main te fixen** |
| `NewDocumentVersionAction.php` | verschilt (zgw-client refactor plus unlock-fix op next/v1.2) | alleen relevant als we de check ook in `createNewDocumentVersion()` afdwingen; die toevoeging is klein en merget naar verwachting clean |

### Wat op de fix-branch vanaf `main` kan (nieuwe beta)

1. **Bevinding B**: exacte match-verificatie in `LocatieserverService::getBagObjectByPostcodeHuisnummer()` plus unit tests.
2. **Bevinding C**: leegmaken plus notificatie in `AddressNL::lookupCallback()` plus tests.
3. **Bevinding A**: Playwright-repro en fix in de fetch-orkestratie van `EventFormPage` (bestanden zijn gelijk op beide branches).
4. **MessageForm-gat** (deel van bevinding D): authorizer-check op de `existing_document`-route, plus de check afdwingen in `createNewDocumentVersion()` zelf.
5. **Regressietest op `ZaakDocumentsTable`** die per rol asserteert of de "Nieuwe versie"-action zichtbaar is, inclusief het aanvraagformulier-geval. Deze test slaagt op main. Na de merge in next/v1.2 faalt hij daar zolang de `->visible()`-override niet gefixt is, en dwingt zo de vervolgstap af.

### Wat apart op `next/v1.2` moet

De `->visible()`-override in `ZaakDocumentsTable.php:112` bestaat alleen daar (binnengekomen met `ea752c64`, de OneGround-optimalisaties). Twee opties:

- **Optie 1 (aanbevolen)**: direct een kleine fix-branch vanaf `next/v1.2` die `->visible(fn () => ! $this->submissionOnly)` vervangt door `->hidden(fn () => $this->submissionOnly)`. Niet wachten op de beta-merge, dit is een security-issue op de geteste omgeving.
- **Optie 2**: meenemen in de merge-commit van de beta naar `next/v1.2`. Nadeel: de fix zit dan verstopt in een merge en de testomgeving blijft langer kwetsbaar.

### Mergevolgorde en controle

1. Fix-branch vanaf `main`, PR, review, mergen naar `main`, nieuwe beta taggen (met kloppende "Full Changelog" compare-range, zie eerdere afspraak).
2. Fix-branch vanaf `next/v1.2` voor de `ZaakDocumentsTable`-override (optie 1 hierboven).
3. Beta mergen in `next/v1.2`. Verwachte aandachtspunten: `LocatieserverService.php` (fl-parameter, beide kanten behouden), `MessageForm.php` en `NewDocumentVersionAction.php` (zgw-client refactor van next/v1.2 laten staan, alleen de authorizer-check en de exact-match-logica overnemen).
4. Na de merge de volledige suite op `next/v1.2` draaien. De nieuwe `ZaakDocumentsTable`-regressietest bewijst dan dat de override-fix en de beta-fixes samen correct zijn.
5. Testomgeving bijwerken en de tester de teststappen uit de instructie opnieuw laten uitvoeren, met expliciet de rij 1 en rij 2 variant uit bevinding A.

## Voorgestelde volgorde

1. **Bevinding D**: security-relevant en klein. Eerst de override-fix op `next/v1.2` (testomgeving), parallel het MessageForm-gat plus regressietests op de fix-branch vanaf `main`.
2. **Bevinding B en C samen** (exacte adresmatch plus leegmaken en melden) op de fix-branch vanaf `main`: afgebakend, server-side, goed testbaar.
3. **Bevinding A** (browser-race) op de fix-branch vanaf `main`: eerst Playwright-repro, daarna gerichte fix.
