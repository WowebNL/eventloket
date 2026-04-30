<?php

declare(strict_types=1);

namespace App\EventForm\State;

use App\EventForm\Support\JsTruthy;

/**
 * Pure-functions-class voor afgeleide variabelen. Vervangt stuk voor
 * stuk de `setVariable`-acties uit de gegenereerde rule-classes door
 * lazy-computed accessors.
 *
 * Werking:
 *   - Elke methode is naam-equivalent aan z'n OF-variabele-naam
 *     (`evenementInGemeentenNamen()` ↔ `'evenementInGemeentenNamen'`).
 *   - `FormState::get()` raadpleegt deze class als de gevraagde key
 *     overeenkomt met een gemigreerde derivatie. Zo blijven templates
 *     en rules-die-nog-niet-gemigreerd-zijn unchanged werken — ze
 *     krijgen automatisch de gecomputeerde waarde.
 *   - Methodes lezen ALLEEN uit de meegegeven `FormState`. Geen side-
 *     effects, geen DB-calls, geen HTTP. Idempotent en goedkoop te
 *     re-evalueren bij elke render.
 *
 * Migratie-regel: als een variabele hier een methode heeft, mag de
 * oude rule die dezelfde naam schreef worden verwijderd. Tijdens de
 * overgang kunnen beide naast elkaar bestaan — FormDerivedState
 * wint dankzij de delegatie in `FormState::get()`.
 */
final class FormDerivedState
{
    /** @var array<string, true> */
    public const COMPUTED_KEYS = [
        'evenementInGemeentenNamen' => true,
        'evenementInGemeentenLijst' => true,
        'binnenVeiligheidsregio' => true,
        'gemeenten' => true,
        'routeDoorGemeentenNamen' => true,
        'evenementInGemeente' => true,
        'alcoholvergunning' => true,
        'isVergunningaanvraag' => true,
        'risicoClassificatie' => true,
        'confirmationtext' => true,
    ];

    /**
     * Velden van de vergunningsplichtig-scan. Als één van deze 'Nee' is
     * (of wegen-afsluiten 'Ja') wordt de aanvraag een vergunning i.p.v.
     * een melding.
     *
     * @var list<string>
     */
    private const SCAN_VRAGEN_NEE = [
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf',
        'vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen',
        'WordtErAlleenMuziekGeluidGeproduceerdTussen',
        'IsdeGeluidsproductieLagerDan',
        'erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten',
        'wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst',
        'indienErObjectenGeplaatstWordenZijnDezeDanKleiner',
        'meldingvraag1',
        'meldingvraag2',
        'meldingvraag3',
        'meldingvraag4',
        'meldingvraag5',
    ];

    /**
     * Risicoscan-vragen waarvan de scores opgeteld worden om de
     * A/B/C-classificatie te bepalen.
     *
     * @var list<string>
     */
    private const RISICOSCAN_VELDEN = [
        'watIsDeAantrekkingskrachtVanHetEvenement',
        'watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep',
        'isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid',
        'isEenDeelVanDeDoelgroepVerminderdZelfredzaam',
        'isErSprakeVanAanwezigheidVanRisicovolleActiviteiten',
        'watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep',
        'isErSprakeVanOvernachten',
        'isErGebruikVanAlcoholEnDrugs',
        'watIsHetAantalGelijktijdigAanwezigPersonen',
        'inWelkSeizoenVindtHetEvenementPlaats',
        'inWelkeLocatieVindtHetEvenementPlaats',
        'opWelkSoortOndergrondVindtHetEvenementPlaats',
        'watIsDeTijdsduurVanHetEvenement',
        'welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing',
    ];

    public function __construct(private readonly FormState $state) {}

    /**
     * Lijst van gemeente-namen die het ingetekende formulier raakt
     * (polygons + lijnen + adressen, gecombineerd via
     * `LocationServerCheckService` in `inGemeentenResponse.all.items`).
     *
     * OF-rule 6f1046a6-7866-491b-b87d-65bd67aade6f
     * → `setVariable('evenementInGemeentenNamen', map(items, 'name'))`
     *
     * @return list<string>
     */
    public function evenementInGemeentenNamen(): array
    {
        // Lijst-derivatie: altijd een array, ook lege. Komt overeen met
        // OF's gedrag waar de rule onvoorwaardelijk fired en de variabele
        // op `[]` zette wanneer er geen items waren.
        return $this->pluckNames($this->state->get('inGemeentenResponse.all.items'));
    }

    /**
     * Per gemeente een gemerged-array van [brk_identification, name].
     * OF gebruikte dit als input voor een aantal templates.
     *
     * OF-rule 6f1046a6-7866-491b-b87d-65bd67aade6f
     * → `setVariable('evenementInGemeentenLijst', map(items, [brk, name]))`
     *
     * @return list<array<int, string>>
     */
    public function evenementInGemeentenLijst(): array
    {
        $items = $this->state->get('inGemeentenResponse.all.items');
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $out[] = array_merge(
                (array) ($item['brk_identification'] ?? []),
                (array) ($item['name'] ?? []),
            );
        }

        return $out;
    }

    /**
     * Of het hele ingetekende gebied binnen Eventloket-gemeenten valt
     * (= true als geen enkel deel "buiten" de regio steekt).
     *
     * OF-rule 6f1046a6-7866-491b-b87d-65bd67aade6f
     * → `setVariable('binnenVeiligheidsregio',
     *                inGemeentenResponse.all.within)`
     */
    public function binnenVeiligheidsregio(): mixed
    {
        // `all.within` is bool|null afhankelijk van of de check is gedaan;
        // wij geven 'm 1-op-1 door zoals OF deed (ja/nee/null).
        return $this->state->get('inGemeentenResponse.all.within');
    }

    /**
     * Map van brk_identification → gemeente-record. Wordt door de
     * `userSelectGemeente`-Radio-options gebruikt en door
     * `evenementInGemeente()` voor lookup-by-keuze.
     *
     * OF-rule 6f1046a6-7866-491b-b87d-65bd67aade6f
     * → `setVariable('gemeenten', inGemeentenResponse.all.object)`
     *
     * @return array<string, array{brk_identification: string, name: string}>|null
     */
    public function gemeenten(): mixed
    {
        return $this->state->get('inGemeentenResponse.all.object');
    }

    /**
     * Namen van de gemeenten waar de ingetekende route doorheen gaat
     * (gebaseerd op `inGemeentenResponse.line.items`).
     *
     * OF-rule 6f1046a6-7866-491b-b87d-65bd67aade6f
     * → `setVariable('routeDoorGemeentenNamen', map(line.items, 'name'))`
     *
     * @return list<string>
     */
    public function routeDoorGemeentenNamen(): array
    {
        return $this->pluckNames($this->state->get('inGemeentenResponse.line.items'));
    }

    /**
     * Welke gemeente verwerkt deze aanvraag? Twee bronnen, in volgorde
     * van precedence:
     *
     *   1. Heeft de gebruiker er één geselecteerd via de
     *      `userSelectGemeente`-Radio (verschijnt bij ≥2 gevonden
     *      gemeenten)? Dan die.
     *   2. Anders, als er precies één gevonden gemeente is, automatisch
     *      die.
     *   3. Anders: null (geen aanvraag mogelijk; UI toont
     *      "buiten Eventloket"-melding).
     *
     * Origineel waren dit twee aparte rules met last-write-wins-volgorde
     * tijdens de fixpoint-loop:
     *
     *   - OF-rule a6fcec40-74f6-4741-862f-22ebf2de7142 — auto-pick bij 1
     *   - OF-rule 580a3ef8-9fa6-4f5a-8714-502d86d6cb55 — userSelectGemeente
     *
     * Hier expliciet als if/else-cascade. De semantiek matcht de oude
     * fixpoint-volgorde: als userSelectGemeente gezet is, wint die
     * altijd, óók wanneer er maar één gevonden gemeente was — exact
     * wat de fixpoint-loop ook deed.
     *
     * @return array<string, mixed>|null
     */
    public function evenementInGemeente(): mixed
    {
        $pick = $this->state->get('userSelectGemeente');
        if (is_string($pick) && $pick !== '') {
            // De `gemeenten`-map is keyed by brk_identification.
            return $this->state->get("gemeenten.{$pick}");
        }

        $items = $this->state->get('inGemeentenResponse.all.items');
        if (is_array($items) && count($items) === 1) {
            return $items[0];
        }

        return null;
    }

    /**
     * Of de ontheffing-aanvraag-flow een alcoholvergunning betreft. Was
     * een rule die `'Ja'` schreef bij een specifieke checkbox.
     *
     * OF-rule b92d2e5a-3ff7-4b1d-91d4-f1ca827247f7
     * → `setVariable('alcoholvergunning', 'Ja')` als
     *   kruisAanWatVanToepassingIsVoorUwEvenementX.A5 === true
     *
     * Originele return-type was string ('Ja') of null. We houden 't
     * 1-op-1 zo zodat templates die `{{ if alcoholvergunning }}` doen
     * onveranderd werken.
     */
    public function alcoholvergunning(): ?string
    {
        return $this->state->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true
            ? 'Ja'
            : null;
    }

    /**
     * Aanvraag wordt een vergunning (i.p.v. een lichtere melding) als de
     * organisator op één van de 12 scan-vragen "Nee" antwoordt OF
     * "wegen afsluiten" op "Ja" zet. OF gebruikte een rule die deze
     * vlag zette; wij maken er een pure-functionele afgeleide van.
     *
     * OF-rule 87482f34-1e1f-4853-b2da-312c9b2cebf0
     * → `setVariable('isVergunningaanvraag', true)` als één van de
     *   scan-vragen 'Nee' is OF wegen-afsluiten 'Ja' is.
     *
     * Originele return-type was bool|null (true bij match, null als
     * niets matched). We houden 't 1-op-1 — `null` zorgt ervoor dat
     * `JsTruthy` 't als false interpreteert.
     */
    public function isVergunningaanvraag(): ?bool
    {
        foreach (self::SCAN_VRAGEN_NEE as $key) {
            if ($this->state->get($key) === 'Nee') {
                return true;
            }
        }
        if ($this->state->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja') {
            return true;
        }

        return null; // door-fall naar values-bag
    }

    /**
     * Risico-classificatie A/B/C op basis van de som van 14 risicoscan-
     * scores. Som ≤ 6 = A, ≤ 9 = B, anders = C. Velden zijn pas
     * "scoorbaar" als ze allemaal een waarde hebben — voorkomt dat
     * partial input een te-lage classificatie geeft.
     *
     * OF-rule 55ce8acd-f972-417d-8920-64c8b0744e14
     * → `setVariable('risicoClassificatie', sum(14 fields) → A/B/C)`
     */
    public function risicoClassificatie(): ?string
    {
        $sum = 0.0;
        foreach (self::RISICOSCAN_VELDEN as $key) {
            $value = $this->state->get($key);
            if (! JsTruthy::of($value)) {
                // Eén veld nog niet ingevuld → geen classificatie. Door-fall
                // zodat een eventueel handmatig gezette waarde wint.
                return null;
            }
            $sum += (float) $value;
        }

        return match (true) {
            $sum <= 6.0 => 'A',
            $sum <= 9.0 => 'B',
            default => 'C',
        };
    }

    /**
     * Bedankt-tekst die op de bevestigingspagina verschijnt. Twee
     * mogelijke bronnen:
     *
     *   - Bij vooraankondiging: lege string (= geen bedankt-tekst,
     *     OF-rule 4e724924-... toonde geen succesbericht voor
     *     vooraankondiging).
     *   - Bij melding (wegen-afsluiten === 'Nee'): expliciete tekst.
     *
     * OF-rules in last-write-wins-volgorde:
     *   - 3a1ac5f3-... — meldingstekst bij wegen=Nee
     *   - 4e724924-... — leeg bij vooraankondiging
     *
     * Vooraankondiging wint omdat in OF die rule alfabetisch later
     * stond en dus laatste schreef. Hier expliciet als volgorde-test.
     */
    public function confirmationtext(): ?string
    {
        if ($this->state->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging') {
            return '';
        }
        if ($this->state->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') {
            return 'Bedankt voor het invullen van de details voor de melding van uw evenement.';
        }

        return null; // door-fall naar values-bag
    }

    /**
     * Roept de juiste methode aan voor een gemigreerde key. Leeg
     * resultaat als de key (nog) niet gemigreerd is.
     */
    public function get(string $key): mixed
    {
        return match ($key) {
            'evenementInGemeentenNamen' => $this->evenementInGemeentenNamen(),
            'evenementInGemeentenLijst' => $this->evenementInGemeentenLijst(),
            'binnenVeiligheidsregio' => $this->binnenVeiligheidsregio(),
            'gemeenten' => $this->gemeenten(),
            'routeDoorGemeentenNamen' => $this->routeDoorGemeentenNamen(),
            'evenementInGemeente' => $this->evenementInGemeente(),
            'alcoholvergunning' => $this->alcoholvergunning(),
            'isVergunningaanvraag' => $this->isVergunningaanvraag(),
            'risicoClassificatie' => $this->risicoClassificatie(),
            'confirmationtext' => $this->confirmationtext(),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function pluckNames(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }
        $names = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                $names[] = $item['name'];
            }
        }

        return $names;
    }
}
