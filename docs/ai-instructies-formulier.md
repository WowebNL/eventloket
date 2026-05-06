# Instructies voor AI-assistenten — werking evenementformulier

Dit document is bedoeld voor AI-assistenten (Claude Code, etc.) die
toekomstige aanpassingen aan het Filament-evenementformulier doen.
Het is een mentaal model + verwijzingen, niet een specificatie. Als
het formulier later van vorm verandert, update dan eerst dit document
voordat je code wijzigt — anders gaat een volgende sessie op
verouderde aannames werken.

## Wat is het formulier

Het evenementloket-formulier is een 17-stappen Filament-wizard waarmee
organisatoren een evenementen-aanvraag indienen bij Veiligheidsregio
Zuid-Limburg. Afhankelijk van antwoorden eindigt de aanvraag op één
van drie zaaktypes: **Evenementenvergunning**, **Melding**, of
**Vooraankondiging**.

Entry: `App\Filament\Organiser\Pages\EventFormPage` op
`/organiser/<tenant>/aanvraag`.

## State-architectuur (CRUCIAAL)

De OF-RulesEngine met 146 JsonLogic-rules is **verwijderd** (zie
`docs/migratie-of-rules.md` voor bewijsvoering). State-derivaties
gebeuren nu pure-functioneel:

```
                         ┌──────────────────────┐
                         │      FormState       │ ← values + system bag
                         │  (waardes + cache)   │
                         └──────────┬───────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────────┐
        │                           │                               │
┌───────▼─────────┐    ┌────────────▼──────────┐   ┌────────────────▼──────────┐
│ FormDerivedState│    │  FormFieldVisibility  │   │   FormStepApplicability   │
│ (afgeleide vars)│    │ (per-veld hidden bool)│   │  (per-stap applicable)    │
└─────────────────┘    └───────────────────────┘   └───────────────────────────┘
                                    │
                       ┌────────────▼──────────┐
                       │ FormSystemDerivedState│
                       │ (registratie-backend) │
                       └───────────────────────┘
```

**Regels** voor het werken met deze klasses:

- Een klasse leest **alleen via `$state->get(...)`** uit FormState. Geen
  side-effects, geen schrijven, geen mutators.
- Iedere methode in `FormDerivedState` retourneert `null` wanneer er
  geen primitieve input is om uit af te leiden — zo valt 't door naar
  de values-bag voor service-fetched waarden zoals `inGemeentenResponse`.
- `FormFieldVisibility::COMPUTED_KEYS` en
  `FormStepApplicability::COMPUTED_STEPS` zijn de master-lists. Veld /
  stap niet erin → default behavior (zichtbaar / applicable).
- `FormState::isFieldHidden()` retourneert: `null` (geen mening, caller
  beslist), `true` (verberg), `false` (toon ook al was de step-default
  hidden). Filament-componenten gebruiken `->hidden(fn ($livewire) =>
  $livewire->state()->isFieldHidden('mijnVeld') !== false)`.

## Side-effects (imperative)

State-derivaties dekken niet alles. Drie families van side-effects
zitten in dunne expliciete code-paden:

| Side-effect | Trigger | Plaats |
|---|---|---|
| HTTP-fetches (gemeente, evenementen-overlap, BAG-intersect) | Veld-wijziging | `EventFormPage::triggerFetchesFor()` → `ServiceFetcher` |
| Session-prefill (user/org-velden) | `mount()` | `EventFormPage::applySessionPrefill()` |
| Zaak-prefill ("herhaal aanvraag") | Query-param `?prefill_from_zaak=<UUID>` | `PrefillLoader::load()` |
| Stale gemeente-keuze opruimen | Na `inGemeentenResponse`-fetch | `EventFormPage::resetStaleGemeenteKeuze()` |
| Draft-save | `updated()` | `DraftStore::save()` (10s throttle) |

`ServiceFetcher` cachet per `FormState`-instance op een input-hash
(`WeakMap`) — dezelfde input → geen DB-query.

## De 17 stappen

| # | Step-class | Trigger applicable=false |
|---|---|---|
| 1 | `ContactgegevensStep` | — (altijd applicable) |
| 2 | `NaamVanHetEvenementStep` | — |
| 3 | `LocatieVanHetEvenement2Step` | — |
| 4 | `TijdenStep` | — |
| 5 | `WaarvoorWiltUHetEventloketGebruikenStep` | — |
| 6 | `Vragenboom2Step` (vergunningsplichtig-scan) | `vooraankondiging` ∨ `wegen=Nee` |
| 7 | `MeldingStep` | `vooraankondiging` ∨ `wegen=Ja` ∨ scan-een-Nee |
| 8 | `RisicoscanStep` | `vooraankondiging` |
| 9-15 | `Vergunningsaanvraag*Step` (7 stappen) | `vooraankondiging` ∨ `wegen=Nee` |
| 16 | `BijlagenStep` | — |
| 17 | `TypeAanvraagStep` | — |

Stap-UUIDs staan als `const UUID = '...'` in elke step-class. Deze
UUIDs zijn wat `FormStepApplicability` op match-keys gebruikt.

### Drie aanvraag-paden

```
[Stap 5: waarvoor]
    │
    ├── 'vooraankondiging' ──→ skip 6-15 ──→ Vooraankondiging-zaaktype
    │
    └── 'evenement'
            │
            ├──[Stap 6: wegen=Ja]──→ skip MeldingStep ──→ Evenementenvergunning
            │
            └──[Stap 6: wegen=Nee]─→ skip vergunning-stappen 9-15 ──→ Melding
```

`ResolveZaaktype` bepaalt het uiteindelijke zaaktype op basis van
gemeente × aanvraag-aard.

## Submit-flow

`SubmitEventForm::execute()` is synchroon + async hybride:

**Synchroon** (gebruiker krijgt direct zaaknummer):
1. `ResolveZaaktype` → het juiste Zaaktype voor (gemeente, aard)
2. `CreateZaakInZGW` → POST naar OpenZaak
3. `CreateLocalZaak` → DB-row met `form_state_snapshot` + `reference_data`
4. `DraftStore::clear()` → halve aanvraag weggooien
5. Audit-log

**Async (Bus::chain)**:
- `AddZaakeigenschappenZGW` (11 OF-eigenschappen)
- `AddEinddatumZGW`
- `UpdateInitiatorZGW`
- `AddGeometryZGW` (location-geometrieën)
- `CreateDoorkomstZaken` (alleen route-events)

**Async (parallel)**:
- `GenerateSubmissionPdf` (high-queue, dispatcht zelf
  `UploadSubmissionPdfToZGW` daarna)
- `UploadFormBijlagenToZGW` (FileUploads als zaakinformatieobjecten)
- `HashIdentifyingAttributes` (BSN/KvK anonimiseren in snapshot)

Notificatie via `SendSubmissionConfirmationEmail` (door
`GenerateSubmissionPdf` na PDF-write). PDF gaat als bijlage mee.

## PDF-rapport

`GenerateSubmissionPdf` rendert `resources/views/pdf/submission-report.blade.php`
op disk in `storage/app/zaken/<zaak_uuid>/submission-report.pdf`. De
inhoud komt uit `SubmissionReport::build($state, $steps)` die per stap
de ingevulde velden + labels verzamelt en groepeert in sections met
key-value entries. Voor verifieer-doeleinden zonder PDF-parsing:
`php artisan eventform:dump-pdf-content <public_id>` print de zelfde
sections als JSON.

## Validatie

Cross-field constraints (datum-relaties) zitten in
`App\EventForm\Validation\TijdenFieldRules` — Filament `->rule(fn () =>
...)`-closures die met `$get` peeken naar andere veld-waardes.

`AVG-akkoord` op de Samenvatting-stap is required + `accepted()` —
zonder vinkje blokkeert validation de Indienen-knop.

## Tests + bewijsvoering

| Test-soort | Locatie | Wat 't bewijst |
|---|---|---|
| Pure-functioneel state-derivaties | `tests/Feature/EventForm/State/` | Per derivation: gegeven input → verwachte output |
| Equivalentie met OF-spec | `tests/Feature/EventForm/Equivalence/` (174 scenarios) | PHP en canonical `json-logic-js` geven dezelfde uitkomst |
| Submit-keten end-to-end | `tests/Feature/EventForm/Submit/SubmitEventFormTest.php` | ZGW-zaak + lokale Zaak + 7 jobs dispatched + email |
| Page-flow + reactivity | `tests/Feature/EventForm/Pages/EventFormPageTest.php` | mount, prefill, draft-restore, gemeente-reset |
| Browser-walkthrough + PDF | `tests/Playwright/scenario-*.spec.mjs` | Drie scenarios door echte Livewire + PDF-content per scenario |

**Reproduceren**:
```bash
docker compose exec laravel.test php artisan test
docker compose exec laravel.test php artisan eventform:export-scenarios
node dev-scripts/verify-scenarios-jsonlogic.mjs   # 174/174 spec-match
docker compose exec laravel.test php artisan eventform:gedrags-rapport
npx playwright test                                # 3 scenario-walkthroughs + reactivity
```

## Veelvoorkomende valkuilen

1. **Niet schrijven naar FormState in `FormDerivedState`-methodes.**
   Pure-functioneel = geen side-effects. Een derivation die `setVariable`
   doet, breekt de cache + introduceert volgorde-afhankelijkheid.

2. **`isFieldHidden(...) !== false` is intentioneel.** Drie waardes zijn
   mogelijk: `true` (force hidden), `false` (force show), `null` (geen
   mening — gebruik step-default). Veld-componenten doen
   `->hidden(fn ($livewire) => $livewire->state()->isFieldHidden('x') !==
   false)` om de logica om te draaien.

3. **`updated()` cleart niet vanzelf.** Mutaties via
   `setField`/`setVariable` invalidate de get-cache. Maar
   `triggerFetchesFor()` schrijft via `ServiceFetcher::fetch()` direct
   in state — geen Livewire-roundtrip nodig om de fetch te zien.

4. **Stappen 9-15 zijn alleen-applicable in vergunning-pad.** Als je
   een nieuw veld op zo'n stap zet en 't lijkt nooit op te slaan in een
   melding-aanvraag: dat is correct gedrag — die stappen worden geskipt.

5. **PDF-content is geen render-test.** Asserties in Playwright
   gebruiken `php artisan eventform:dump-pdf-content` (= dezelfde data
   die de Blade krijgt). Visuele PDF-output testen we niet — wijzigingen
   aan de Blade vereist visuele review via `php artisan
   eventform:genereer-demo-pdf`.

6. **De OF-export blijft als referentie.** `docker/local-data/open-formulier/`
   bevat de originele JsonLogic + step-definities — door
   `dev-scripts/verify-scenarios-jsonlogic.mjs` als spec-anchor gebruikt.
   Niet weggooien.

## Wijziging maken — checklist

- [ ] Lees deze doc helemaal
- [ ] Bepaal: gaat 't om afgeleide state, visibility, applicability, of
      side-effect? → één van de vier klasses + helpers
- [ ] Update de juiste pure-functional klasse OF voeg een handler toe
      aan `EventFormPage`
- [ ] Schrijf een unit-test (`tests/Feature/EventForm/State/...` of een
      `Pages/EventFormPageTest`-case)
- [ ] Voeg desnoods een scenario toe in een `ScenarioProvider`-class
      (gedragsspecificatie regenereert dan automatisch)
- [ ] Zet `php artisan test` + `php artisan pint` + Playwright groen
- [ ] Update `docs/migratie-of-rules.md` als je een rule "afmaakt" of
      verschuift
- [ ] Update deze doc als je een architectuur-aanname verandert
