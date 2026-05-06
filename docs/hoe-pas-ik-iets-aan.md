# Hoe pas ik iets aan in het evenementformulier?

Praktische gids voor wijzigingen aan het Filament-evenementformulier.
Geen theorie — een beslisboom + uitgewerkte voorbeelden zodat je direct
weet waar je moet zijn.

Voor het mentale model + architectuur-context, zie
[`ai-instructies-formulier.md`](ai-instructies-formulier.md).

## Beslisboom — "ik wil X aanpassen, waar ga ik heen?"

| Wat je wilt doen | Waar je heen gaat |
|---|---|
| Veld toevoegen / label wijzigen / opties aanpassen | `app/EventForm/Schema/Steps/<X>Step.php` |
| Veld conditioneel verbergen (afhankelijk van ander veld) | `FormFieldVisibility::<veldnaam>()` |
| Stap overslaan op basis van antwoorden | `FormStepApplicability` (match-arm op step-UUID) |
| Afgeleide waarde berekenen (bv. risico-classificatie) | `FormDerivedState::<naam>()` |
| Externe HTTP-call (BAG, gemeente-data, etc.) | `ServiceFetcher::fetch<X>` + trigger in `EventFormPage::triggerFetchesFor()` |
| Validatie-regel (cross-field datums, required, etc.) | `app/EventForm/Validation/<X>FieldRules.php` of inline `->rule(...)` op het veld |
| Stap-volgorde / nieuwe stap toevoegen | `app/EventForm/Schema/EventFormSchema.php` |
| PDF-rapport inhoud / styling | `resources/views/pdf/submission-report.blade.php` (HTML) + `app/EventForm/Reporting/SubmissionReport.php` (data) |
| Async werk na submit (ZGW-jobs, mail, hash) | `app/Jobs/Submit/*` + `SubmitEventForm::dispatchAsyncChain()` |

**Eén kernregel** — er zijn twee categorieën logica:

1. **State-derivaties** — pure-functioneel: alleen lezen uit FormState,
   niets opslaan. Vier klasses (`FormDerivedState`, `FormFieldVisibility`,
   `FormStepApplicability`, `FormSystemDerivedState`).
2. **Side-effects** — HTTP-calls, draft opslaan, veld-waarden resetten.
   Allemaal in `EventFormPage` (`triggerFetchesFor`, `applySessionPrefill`,
   `resetStaleGemeenteKeuze`, `submit`).

---

## Voorbeeld 1: een nieuw veld toevoegen op een stap

**Doel**: een vraag *"Heeft uw evenement een EHBO-post?"* met Ja/Nee
toevoegen aan stap "Vergunningsaanvraag: voorzieningen".

**Stap 1** — open de juiste step-class:

```php
// app/EventForm/Schema/Steps/VergunningsaanvraagVoorzieningenStep.php

return Step::make('Vergunningsaanvraag: voorzieningen')
    ->key(self::UUID)
    ->schema([
        // ... bestaande velden ...

        Radio::make('heeftEvenementEhboPost')
            ->label('Heeft uw evenement een eigen EHBO-post?')
            ->options(['Ja' => 'Ja', 'Nee' => 'Nee'])
            ->required()
            ->live(),
    ]);
```

**Stap 2** — geen migratie nodig: het antwoord komt automatisch in
`form_state_snapshot` via `FormState::absorbFields()` op de Zaak.

**Stap 3** — test:

```php
// tests/Feature/EventForm/Schema/VoorzieningenStepTest.php

test('heeftEvenementEhboPost wordt opgeslagen in de FormState', function () {
    $state = FormState::empty();
    $state->setField('heeftEvenementEhboPost', 'Ja');

    expect($state->get('heeftEvenementEhboPost'))->toBe('Ja');
});
```

Geen verdere wiring nodig — Filament pakt 't veld automatisch op.

---

## Voorbeeld 2: een veld conditioneel verbergen

**Doel**: het veld *"aantal EHBO'ers"* alleen tonen als
`heeftEvenementEhboPost === 'Ja'`.

**Stap 1** — voeg het veld toe + verwijs naar `FormFieldVisibility`:

```php
// In dezelfde step-file:

TextInput::make('aantalEhboers')
    ->label('Aantal EHBO\'ers')
    ->numeric()
    ->required()
    ->hidden(fn ($livewire): bool =>
        $livewire->state()->isFieldHidden('aantalEhboers') !== false)
    ->live(),
```

> De `!== false` is intentioneel. `isFieldHidden()` returnt `null`
> (geen mening), `true` (verberg), of `false` (toon ondanks default).
> `!== false` betekent: "verberg tenzij FormFieldVisibility expliciet
> zegt 'toon'".

**Stap 2** — voeg de visibility-rule toe:

```php
// app/EventForm/State/FormFieldVisibility.php

public const COMPUTED_KEYS = [
    // ... bestaande keys ...
    'aantalEhboers' => true,
];

public function get(string $fieldKey): ?bool
{
    $s = $this->state;

    return match ($fieldKey) {
        // ... bestaande arms ...

        'aantalEhboers' => $s->get('heeftEvenementEhboPost') === 'Ja'
            ? false   // toon
            : null,   // door-fall naar default (= verborgen)

        default => null,
    };
}
```

**Stap 3** — test:

```php
// tests/Feature/EventForm/State/FormFieldVisibilityTest.php

test('aantalEhboers verschijnt als ehbo-post=Ja', function () {
    $state = new FormState(values: ['heeftEvenementEhboPost' => 'Ja']);
    expect($state->isFieldHidden('aantalEhboers'))->toBeFalse(); // toon
});

test('aantalEhboers blijft verborgen bij Nee', function () {
    $state = new FormState(values: ['heeftEvenementEhboPost' => 'Nee']);
    expect($state->isFieldHidden('aantalEhboers'))->toBeNull(); // door-fall = hidden
});
```

---

## Voorbeeld 3: een afgeleide waarde berekenen

**Doel**: een afgeleide `vereistMedischeBegeleiding` die `true` is als
het evenement >5000 personen heeft *of* alcohol-vergunning *of* het
risicoprofiel C is.

**Stap 1** — voeg de afgeleide toe aan `FormDerivedState`:

```php
// app/EventForm/State/FormDerivedState.php

public const COMPUTED_KEYS = [
    // ... bestaande keys ...
    'vereistMedischeBegeleiding' => true,
];

public function vereistMedischeBegeleiding(): ?bool
{
    $s = $this->state;
    $aantal = (int) $s->get('watIsHetMaximaalAanwezigeAantal...');

    if ($aantal > 5000) {
        return true;
    }
    if ($s->get('alcoholvergunning') === 'Ja') {
        return true;
    }
    if ($s->get('risicoClassificatie') === 'C') {
        return true;
    }

    return null; // door-fall naar values-bag (= geen mening)
}

// En in de get()-dispatcher onderaan de class:
public function get(string $key): mixed
{
    return match ($key) {
        // ... bestaande arms ...
        'vereistMedischeBegeleiding' => $this->vereistMedischeBegeleiding(),
        default => null,
    };
}
```

**Stap 2** — gebruik in templates / labels:

```php
TextEntry::make('medischeBegeleidingHint')
    ->state(fn ($livewire) => new HtmlString(
        app(LabelRenderer::class)->render(
            '<p>Voor uw evenement is medische begeleiding verplicht.</p>',
            $livewire->state()
        )
    ))
    ->hidden(fn ($livewire): bool =>
        $livewire->state()->get('vereistMedischeBegeleiding') !== true);
```

**Stap 3** — test:

```php
// tests/Feature/EventForm/State/FormDerivedStateEquivalenceTest.php

test('vereistMedischeBegeleiding bij grootschalig evenement', function () {
    $state = new FormState(values: [
        'watIsHetMaximaalAanwezigeAantal...' => 6000,
    ]);
    expect((new FormDerivedState($state))->get('vereistMedischeBegeleiding'))
        ->toBeTrue();
});
```

---

## Voorbeeld 4: een externe HTTP-call chainen

**Doel**: na de BAG-lookup óók de naburige zaakprocesvoerder bij de
gemeente ophalen via een nieuwe API-call.

**Stap 1** — voeg de fetch-case toe aan ServiceFetcher:

```php
// app/EventForm/Services/ServiceFetcher.php

public function fetch(string $variable, FormState $state): void
{
    // ... bestaande hash-cache check ...

    match ($variable) {
        'eventloketSession' => $this->fetchEventloketSession($state),
        'gemeenteVariabelen' => $this->fetchGemeenteVariabelen($state),
        'evenementenInDeGemeente' => $this->fetchEvenementenInDeGemeente($state),
        'inGemeentenResponse' => $this->fetchInGemeentenResponse($state),
        'zaakprocesvoerder' => $this->fetchZaakprocesvoerder($state), // ← nieuw
        default => null,
    };
}

private function inputHashFor(string $variable, FormState $state): ?string
{
    return match ($variable) {
        // ... bestaande arms ...
        'zaakprocesvoerder' =>
            sha1((string) $state->get('evenementInGemeente.brk_identification')),
        default => null,
    };
}

private function fetchZaakprocesvoerder(FormState $state): void
{
    $brkId = $state->get('evenementInGemeente.brk_identification');
    if (! is_string($brkId) || $brkId === '') {
        return;
    }

    $procesvoerder = $this->zaakprocesvoerderService->voor($brkId);
    $state->setVariable('zaakprocesvoerder', $procesvoerder);
}
```

**Stap 2** — trigger 'm bij relevant veld in EventFormPage:

```php
// app/Filament/Organiser/Pages/EventFormPage.php

private function triggerFetchesFor(string $propertyName): void
{
    // ... bestaande triggers ...

    if (in_array($field, ['locatieSOpKaart', 'routesOpKaart', 'adresVanDeGebouwEn'], true)) {
        $fetcher->fetch('inGemeentenResponse', $this->state);
        $this->resetStaleGemeenteKeuze();
        $fetcher->fetch('gemeenteVariabelen', $this->state);
        $fetcher->fetch('evenementenInDeGemeente', $this->state);
        $fetcher->fetch('zaakprocesvoerder', $this->state); // ← nieuw
    }
}
```

> ServiceFetcher cachet intern op input-hash (per FormState-instance,
> via WeakMap). Dezelfde input → geen extra DB/HTTP-werk. Dus
> over-fetchen is goedkoop.

**Stap 3** — test:

```php
// tests/Feature/EventForm/Services/ServiceFetcherTest.php

test('zaakprocesvoerder fetch op brk_identification', function () {
    Http::fake(['*' => Http::response(['naam' => 'Behandelaar A'])]);

    $state = FormState::empty();
    $state->setVariable('evenementInGemeente', ['brk_identification' => 'GM0917']);

    app(ServiceFetcher::class)->fetch('zaakprocesvoerder', $state);

    expect($state->get('zaakprocesvoerder.naam'))->toBe('Behandelaar A');
});
```

---

## Voorbeeld 5: een stap overslaan op basis van antwoorden

**Doel**: stap "Risicoscan" (UUID `c75cc256-…`) overslaan als
`heeftEvenementEhboPost === 'Ja'` (verzonnen voorbeeld).

**Stap 1** — voeg de UUID toe aan `COMPUTED_STEPS`:

```php
// app/EventForm/State/FormStepApplicability.php

public const COMPUTED_STEPS = [
    // ... bestaande UUIDs ...
    'c75cc256-6729-4684-9f9b-ede6265b3e72' => true,
];

public function get(string $stepUuid): ?bool
{
    $s = $this->state;

    return match ($stepUuid) {
        // ... bestaande arms ...

        'c75cc256-6729-4684-9f9b-ede6265b3e72' => (function () use ($s): ?bool {
            if ($s->get('heeftEvenementEhboPost') === 'Ja') {
                return false; // niet applicable
            }
            return null; // door-fall naar default (= applicable)
        })(),

        default => null,
    };
}
```

**Stap 2** — `VerticalWizard` skipt de stap automatisch en de
sidebar toont 'm doorgestreept. Geen verdere wiring nodig.

**Stap 3** — test:

```php
test('Risicoscan-stap overgeslagen bij EHBO-post=Ja', function () {
    $state = new FormState(values: ['heeftEvenementEhboPost' => 'Ja']);
    expect($state->isStepApplicable('c75cc256-6729-4684-9f9b-ede6265b3e72'))
        ->toBeFalse();
});
```

---

## Veelvoorkomende valkuilen

1. **Pure-functioneel = geen state schrijven in derivation-classes.**
   Een methode in `FormDerivedState` mag nooit `setVariable()`
   aanroepen. Alleen lezen via `$this->state->get(...)`. Anders breekt
   de cache + introduceer je volgorde-afhankelijkheid.

2. **`isFieldHidden(...) !== false` is intentioneel.** Drie waardes:
   `true` (force hidden), `false` (force show), `null` (geen mening —
   gebruik step-default, meestal hidden). De `!== false` betekent
   "verberg tenzij FormFieldVisibility expliciet zegt 'toon'".

3. **Niet alle stappen zijn altijd applicable.** Stappen 9-15
   (vergunning-tak) zijn alleen actief in vergunning-pad. Stap 7
   (Melding) alleen in melding-pad. Schema → vooraankondiging-pad
   skipt 6-15. Bij testen: kies de juiste pad-flag.

4. **`->live()` is nodig op velden waar visibility/applicability van
   afhangt.** Zonder `->live()` triggert Filament geen
   Livewire-roundtrip → state update wordt pas bij Volgende-klik
   gezien.

5. **Map-componenten (`Map::make`) hebben GEEN `->live()`.** Daarom
   triggert intekenen van een polygon geen automatische gemeente-
   detect. Workaround: `triggerFetchesFor` wordt al getriggerd als de
   map-state in `data` muteert (via Livewire's `updated()`-hook), maar
   alleen als de map-component een wire:model-binding heeft die ook
   updates verzendt. Bij twijfel: handmatig in `EventFormPage::updated`
   debuggen.

6. **Tests altijd schrijven vóór je `[completed]` zegt.** Pure-functional
   methodes lenen zich uitstekend voor unit-tests met `FormState` als
   enige input. Geen DB nodig (tenzij je een ServiceFetcher test).

## Debuggen

```bash
# Run één specifieke test
docker compose exec laravel.test php artisan test \
  --filter="naam-van-de-test"

# Run alle EventForm-tests
docker compose exec laravel.test php artisan test tests/Feature/EventForm

# Linting + auto-fix
docker compose exec laravel.test ./vendor/bin/pint app/EventForm

# Static analysis
docker compose exec laravel.test ./vendor/bin/phpstan analyse --memory-limit=2G
```

Live debug in browser:

```bash
./vendor/bin/sail npm run dev          # Vite-dev voor hot-reload
./vendor/bin/sail artisan queue:listen # Voor async jobs (PDF, ZGW-uploads)
```

Daarna `/organiser/<tenant>/aanvraag` openen. Als state niet update
tijdens invullen: check in browser-devtools of er Livewire-roundtrips
zijn (tab "Network" → filter op `livewire/update`).

## Cookbook-overzicht

Per veld-soort wat extra werk er nodig is:

| Soort wijziging | Files aanraken |
|---|---|
| Simpel veld (radio/text) | 1 (Step) |
| Veld met conditional show | 2 (Step + FormFieldVisibility) |
| Afgeleide variabele | 1-2 (FormDerivedState + Step die 'm gebruikt) |
| Nieuwe HTTP-fetch | 2-3 (ServiceFetcher + EventFormPage + optioneel een Service-class) |
| Stap overslaan | 1 (FormStepApplicability) |
| Nieuwe stap | 2-3 (nieuwe Step-class + EventFormSchema + eventueel SubmissionReport) |
| PDF-aanpassing | 1-2 (Blade-template + optioneel SubmissionReport) |

Hoe meer afgeleid → hoe minder velden je hoeft aan te raken om
gedrag te wijzigen. Dat is het hele punt van de pure-functionele
state — bedrag-logica los van veld-rendering.
