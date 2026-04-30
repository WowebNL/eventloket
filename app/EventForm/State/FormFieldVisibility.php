<?php

declare(strict_types=1);

namespace App\EventForm\State;

use App\EventForm\Support\JsTruthy;

/**
 * Pure-functions-class voor veld-zichtbaarheid. Gegenereerd uit de
 * 144 transpiled rule-files via dev-scripts/generate-field-visibility.php.
 *
 * Werking: per OF-veld een methode die `bool|null` retourneert:
 *   - `true`  → veld moet verborgen zijn
 *   - `false` → veld moet zichtbaar zijn
 *   - `null`  → geen mening; step-file valt terug op default-logic
 *
 * `FormState::isFieldHidden()` raadpleegt deze class eerst. Bij `null`
 * valt 't door naar de oude `fieldHiddenOverrides`-bag (die de engine
 * vult zolang die nog draait).
 *
 * Alle methodes zijn pure-functioneel — dezelfde input geeft dezelfde
 * output, geen state-accumulatie. Toggle-veiligheid: switch-back werkt
 * automatisch correct, geen `reset...Overrides()` nodig.
 */
final class FormFieldVisibility
{
    public function __construct(private readonly FormState $state) {}

    // === GEGENEREERD via dev-scripts/generate-field-visibility.php ===
    // Aantal velden: 76

    /** @var array<string, true> */
    public const COMPUTED_KEYS = [
        'ContentOverigeBijlage' => true,
        'Lichtmasten' => true,
        'MeldingTekst' => true,
        'NotWithin' => true,
        'Speeltoestellen' => true,
        'aanpassenLocatieEnOfVerwijderenStraatmeubilair' => true,
        'adresVanDeGebouwEn' => true,
        'adresgegevens' => true,
        'alcoholischeDranken' => true,
        'algemeneVragen' => true,
        'andersGroup' => true,
        'bebordingsEnBewegwijzeringsplan' => true,
        'belemmeringVanVerkeer' => true,
        'beveiligers1' => true,
        'bouwsels' => true,
        'bouwsels10MSup2Sup' => true,
        'brandgevaarlijkeStoffen' => true,
        'brandstofopslag' => true,
        'content200' => true,
        'contentBalon' => true,
        'contentDieren' => true,
        'contentGemeenteMelding' => true,
        'contentGoNext' => true,
        'contentLasershow' => true,
        'contentRouteDoorkuistMeerdereGemeenteInfo' => true,
        'contentTattoo' => true,
        'contentVuurkorf' => true,
        'contentVuurwerk' => true,
        'contentWapen' => true,
        'contentZeppelin' => true,
        'douches' => true,
        'ehbo' => true,
        'etenBereidenOfVerkopen' => true,
        'evenmentenInDeBuurtContent' => true,
        'extraAfval' => true,
        'geluidstorens' => true,
        'groteVoertuigen' => true,
        'infoTekstVeiligheidsplan' => true,
        'kansspelen' => true,
        'loadUserInformation' => true,
        'locatieSOpKaart' => true,
        'marktkramen' => true,
        'meldingvraag1' => true,
        'meldingvraag2' => true,
        'meldingvraag3' => true,
        'meldingvraag4' => true,
        'meldingvraag5' => true,
        'metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX' => true,
        'opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2.locatieVanOvernachtenDoorPersoneelOrganisatie1' => true,
        'opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1' => true,
        'organisatieInformatie' => true,
        'overkappingen' => true,
        'overnachtingen' => true,
        'podia' => true,
        'risicoClassificatieContent' => true,
        'tenten' => true,
        'toegangVoorHulpdienstenIsBeperkt' => true,
        'userSelectGemeente' => true,
        'veiligheidsplan' => true,
        'verkeersregelaars' => true,
        'verkooppuntenCashless' => true,
        'verkooppuntenMuntenEnBonnen' => true,
        'verkooppuntenToegangsKaarten' => true,
        'versterkteMuziek' => true,
        'vervoersmaatregelen' => true,
        'verzorgingVanKinderenJongerDan12Jaar' => true,
        'voorwerpen' => true,
        'wCs' => true,
        'waarschuwingGeenKvk' => true,
        'watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc' => true,
        'watVoorBouwselsPlaatsUOpDeLocaties' => true,
        'wegOfVaarwegAfsluiten' => true,
        'welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement' => true,
        'welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX' => true,
        'wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning' => true,
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => true,
    ];

    public function get(string $key): ?bool
    {
        return match ($key) {
            'ContentOverigeBijlage' => $this->ContentOverigeBijlage(),
            'Lichtmasten' => $this->Lichtmasten(),
            'MeldingTekst' => $this->MeldingTekst(),
            'NotWithin' => $this->NotWithin(),
            'Speeltoestellen' => $this->Speeltoestellen(),
            'aanpassenLocatieEnOfVerwijderenStraatmeubilair' => $this->aanpassenLocatieEnOfVerwijderenStraatmeubilair(),
            'adresVanDeGebouwEn' => $this->adresVanDeGebouwEn(),
            'adresgegevens' => $this->adresgegevens(),
            'alcoholischeDranken' => $this->alcoholischeDranken(),
            'algemeneVragen' => $this->algemeneVragen(),
            'andersGroup' => $this->andersGroup(),
            'bebordingsEnBewegwijzeringsplan' => $this->bebordingsEnBewegwijzeringsplan(),
            'belemmeringVanVerkeer' => $this->belemmeringVanVerkeer(),
            'beveiligers1' => $this->beveiligers1(),
            'bouwsels' => $this->bouwsels(),
            'bouwsels10MSup2Sup' => $this->bouwsels10MSup2Sup(),
            'brandgevaarlijkeStoffen' => $this->brandgevaarlijkeStoffen(),
            'brandstofopslag' => $this->brandstofopslag(),
            'content200' => $this->content200(),
            'contentBalon' => $this->contentBalon(),
            'contentDieren' => $this->contentDieren(),
            'contentGemeenteMelding' => $this->contentGemeenteMelding(),
            'contentGoNext' => $this->contentGoNext(),
            'contentLasershow' => $this->contentLasershow(),
            'contentRouteDoorkuistMeerdereGemeenteInfo' => $this->contentRouteDoorkuistMeerdereGemeenteInfo(),
            'contentTattoo' => $this->contentTattoo(),
            'contentVuurkorf' => $this->contentVuurkorf(),
            'contentVuurwerk' => $this->contentVuurwerk(),
            'contentWapen' => $this->contentWapen(),
            'contentZeppelin' => $this->contentZeppelin(),
            'douches' => $this->douches(),
            'ehbo' => $this->ehbo(),
            'etenBereidenOfVerkopen' => $this->etenBereidenOfVerkopen(),
            'evenmentenInDeBuurtContent' => $this->evenmentenInDeBuurtContent(),
            'extraAfval' => $this->extraAfval(),
            'geluidstorens' => $this->geluidstorens(),
            'groteVoertuigen' => $this->groteVoertuigen(),
            'infoTekstVeiligheidsplan' => $this->infoTekstVeiligheidsplan(),
            'kansspelen' => $this->kansspelen(),
            'loadUserInformation' => $this->loadUserInformation(),
            'locatieSOpKaart' => $this->locatieSOpKaart(),
            'marktkramen' => $this->marktkramen(),
            'meldingvraag1' => $this->meldingvraag1(),
            'meldingvraag2' => $this->meldingvraag2(),
            'meldingvraag3' => $this->meldingvraag3(),
            'meldingvraag4' => $this->meldingvraag4(),
            'meldingvraag5' => $this->meldingvraag5(),
            'metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX' => $this->metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX(),
            'opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2.locatieVanOvernachtenDoorPersoneelOrganisatie1' => $this->opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2_locatieVanOvernachtenDoorPersoneelOrganisatie1(),
            'opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1' => $this->opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1(),
            'organisatieInformatie' => $this->organisatieInformatie(),
            'overkappingen' => $this->overkappingen(),
            'overnachtingen' => $this->overnachtingen(),
            'podia' => $this->podia(),
            'risicoClassificatieContent' => $this->risicoClassificatieContent(),
            'tenten' => $this->tenten(),
            'toegangVoorHulpdienstenIsBeperkt' => $this->toegangVoorHulpdienstenIsBeperkt(),
            'userSelectGemeente' => $this->userSelectGemeente(),
            'veiligheidsplan' => $this->veiligheidsplan(),
            'verkeersregelaars' => $this->verkeersregelaars(),
            'verkooppuntenCashless' => $this->verkooppuntenCashless(),
            'verkooppuntenMuntenEnBonnen' => $this->verkooppuntenMuntenEnBonnen(),
            'verkooppuntenToegangsKaarten' => $this->verkooppuntenToegangsKaarten(),
            'versterkteMuziek' => $this->versterkteMuziek(),
            'vervoersmaatregelen' => $this->vervoersmaatregelen(),
            'verzorgingVanKinderenJongerDan12Jaar' => $this->verzorgingVanKinderenJongerDan12Jaar(),
            'voorwerpen' => $this->voorwerpen(),
            'wCs' => $this->wCs(),
            'waarschuwingGeenKvk' => $this->waarschuwingGeenKvk(),
            'watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc' => $this->watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc(),
            'watVoorBouwselsPlaatsUOpDeLocaties' => $this->watVoorBouwselsPlaatsUOpDeLocaties(),
            'wegOfVaarwegAfsluiten' => $this->wegOfVaarwegAfsluiten(),
            'welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement' => $this->welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement(),
            'welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX' => $this->welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX(),
            'wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning' => $this->wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning(),
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => $this->wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer(),
            default => null,
        };
    }

    /**
     * `ContentOverigeBijlage`-veld zichtbaarheid.
     *  - OF-rule f1202010-b8b7-45c0-8f31-756190313451 → show wanneer: ($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C')
     */
    public function ContentOverigeBijlage(): ?bool
    {
        $s = $this->state;
        if ((($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C'))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `Lichtmasten`-veld zichtbaarheid.
     *  - OF-rule e9cf76d6-9eca-4d23-b546-f6f4a9c4d471 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true
     */
    public function Lichtmasten(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `MeldingTekst`-veld zichtbaarheid.
     *  - OF-rule 8e022b2c-1742-4ff7-a5a0-50d02d05833e → show wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
     *  - OF-rule 87482f34-1e1f-4853-b2da-312c9b2cebf0 → hide wanneer: ($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja')
     */
    public function MeldingTekst(): ?bool
    {
        $s = $this->state;
        if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')) {
            return false; // show
        }
        if ((($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja'))) {
            return true; // hide
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `NotWithin`-veld zichtbaarheid.
     *  - OF-rule 9ac0b4c7-ea17-48c4-9bd0-b760ed0570ba → show wanneer: $s->get('binnenVeiligheidsregio') === false
     */
    public function NotWithin(): ?bool
    {
        $s = $this->state;
        if (($s->get('binnenVeiligheidsregio') === false)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `Speeltoestellen`-veld zichtbaarheid.
     *  - OF-rule acc04d68-e446-4c59-b8a5-d40ef246ee74 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true
     */
    public function Speeltoestellen(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `aanpassenLocatieEnOfVerwijderenStraatmeubilair`-veld zichtbaarheid.
     *  - OF-rule 0ab47106-f334-492a-b676-a98ca88c2a64 → show wanneer: $s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32') === true
     */
    public function aanpassenLocatieEnOfVerwijderenStraatmeubilair(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `adresVanDeGebouwEn`-veld zichtbaarheid.
     *  - OF-rule 5e689e7d-0a06-4301-ada5-d36132b285cb → show wanneer: $s->get('waarVindtHetEvenementPlaats.gebouw') === true
     */
    public function adresVanDeGebouwEn(): ?bool
    {
        $s = $this->state;
        if (($s->get('waarVindtHetEvenementPlaats.gebouw') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `adresgegevens`-veld zichtbaarheid.
     *  - OF-rule ce043762-6d77-44dc-8e8c-cb605e9acdfa → hide wanneer: JsTruthy::of($s->get('eventloketSession.kvk'))
     *  - OF-rule 1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a → show wanneer: $s->get('eventloketSession.kvk') === ''
     */
    public function adresgegevens(): ?bool
    {
        $s = $this->state;
        if (($s->get('eventloketSession.kvk') === '')) {
            return false; // show
        }
        if ((JsTruthy::of($s->get('eventloketSession.kvk')))) {
            return true; // hide
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `alcoholischeDranken`-veld zichtbaarheid.
     *  - OF-rule b92d2e5a-3ff7-4b1d-91d4-f1ca827247f7 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true
     */
    public function alcoholischeDranken(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `algemeneVragen`-veld zichtbaarheid.
     *  - OF-rule 47620576-e866-4f7e-98fb-cad476f4ac3b → show wanneer: JsTruthy::of($s->get('evenementInGemeente.brk_identification'))
     *  - OF-rule 6b2aeed1-8226-4a7c-9801-bbe61d576dca → show wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'evenement'
     *  - OF-rule b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08 → show wanneer: $s->get('evenementInGemeente') !== ''
     */
    public function algemeneVragen(): ?bool
    {
        $s = $this->state;
        if ((JsTruthy::of($s->get('evenementInGemeente.brk_identification'))) || ($s->get('waarvoorWiltUEventloketGebruiken') === 'evenement') || ($s->get('evenementInGemeente') !== '')) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `andersGroup`-veld zichtbaarheid.
     *  - OF-rule e0d010cd-193d-4a26-8a01-89b185d5709e → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true
     */
    public function andersGroup(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `bebordingsEnBewegwijzeringsplan`-veld zichtbaarheid.
     *  - OF-rule 457c34ac-d4ac-4037-83b2-eaea58d24ccb → show wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A50') === true
     */
    public function bebordingsEnBewegwijzeringsplan(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A50') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `belemmeringVanVerkeer`-veld zichtbaarheid.
     *  - OF-rule 8893efa1-663a-4ad6-9184-46ae7cb2ebf7 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A8') === true
     */
    public function belemmeringVanVerkeer(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A8') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `beveiligers1`-veld zichtbaarheid.
     *  - OF-rule d5681327-869c-4a3a-be73-88c973668af1 → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A53') === true
     */
    public function beveiligers1(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A53') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `bouwsels`-veld zichtbaarheid.
     *  - OF-rule 145ceec2-91c7-4e67-8195-2444d734ddfc → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A20') === true
     *  - OF-rule 21e363f3-9ca8-42d4-b52e-bddfab43ddd6 → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true
     *  - OF-rule 889aed1d-d7bc-4a93-b5b6-00c01f812724 → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A21') === true
     *  - OF-rule d8d28395-9e5e-4570-a4f3-129ad988ae8f → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A19') === true
     */
    public function bouwsels(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A20') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A21') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A19') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `bouwsels10MSup2Sup`-veld zichtbaarheid.
     *  - OF-rule 8e1a11b9-59f2-407b-8fb1-0fbee9712c08 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true
     */
    public function bouwsels10MSup2Sup(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `brandgevaarlijkeStoffen`-veld zichtbaarheid.
     *  - OF-rule 2d10885d-3e3a-4df1-a17b-d979668d2581 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true
     */
    public function brandgevaarlijkeStoffen(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `brandstofopslag`-veld zichtbaarheid.
     *  - OF-rule 2d10885d-3e3a-4df1-a17b-d979668d2581 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true
     */
    public function brandstofopslag(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `content200`-veld zichtbaarheid.
     *  - OF-rule b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08 → show wanneer: $s->get('evenementInGemeente') !== ''
     */
    public function content200(): ?bool
    {
        $s = $this->state;
        if (($s->get('evenementInGemeente') !== '')) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentBalon`-veld zichtbaarheid.
     *  - OF-rule 35501489-2e07-4d62-b5df-da1b4795d5e7 → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37') === true
     */
    public function contentBalon(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentDieren`-veld zichtbaarheid.
     *  - OF-rule 72e81725-03fc-4c6e-8218-603bc7f07ef8 → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true
     */
    public function contentDieren(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentGemeenteMelding`-veld zichtbaarheid.
     *  - OF-rule 6b2aeed1-8226-4a7c-9801-bbe61d576dca → show wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'evenement'
     *  - OF-rule b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08 → show wanneer: $s->get('evenementInGemeente') !== ''
     */
    public function contentGemeenteMelding(): ?bool
    {
        $s = $this->state;
        if (($s->get('waarvoorWiltUEventloketGebruiken') === 'evenement') || ($s->get('evenementInGemeente') !== '')) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentGoNext`-veld zichtbaarheid.
     *  - OF-rule 8e022b2c-1742-4ff7-a5a0-50d02d05833e → hide wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
     *  - OF-rule 87482f34-1e1f-4853-b2da-312c9b2cebf0 → show wanneer: ($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja')
     */
    public function contentGoNext(): ?bool
    {
        $s = $this->state;
        if ((($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja'))) {
            return false; // show
        }
        if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')) {
            return true; // hide
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentLasershow`-veld zichtbaarheid.
     *  - OF-rule 199313af-cc35-4409-8398-294c658ae03f → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38') === true
     */
    public function contentLasershow(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentRouteDoorkuistMeerdereGemeenteInfo`-veld zichtbaarheid.
     *  - OF-rule 3247522b-8603-4c7c-ae8d-b92a75fb35d6 → show wanneer: JsTruthy::of($s->get('routeDoorGemeentenNamen')) && ((is_array($s->get('routeDoorGemeentenNamen')) ? count($s->get('routeDoorGemeentenNamen')) : 0) >= 2) && JsTruthy::of($s->get('userSelectGemeente11'))
     */
    public function contentRouteDoorkuistMeerdereGemeenteInfo(): ?bool
    {
        $s = $this->state;
        if ((JsTruthy::of($s->get('routeDoorGemeentenNamen')) && ((is_array($s->get('routeDoorGemeentenNamen')) ? count($s->get('routeDoorGemeentenNamen')) : 0) >= 2) && JsTruthy::of($s->get('userSelectGemeente11')))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentTattoo`-veld zichtbaarheid.
     *  - OF-rule 945f1606-e086-4999-983b-8b9c83dab421 → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42') === true
     */
    public function contentTattoo(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentVuurkorf`-veld zichtbaarheid.
     *  - OF-rule ad8eb74d-08d5-4813-9c00-a914f6618300 → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43') === true
     */
    public function contentVuurkorf(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentVuurwerk`-veld zichtbaarheid.
     *  - OF-rule ad564ba5-b144-438a-9449-dda1800ecbd3 → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41') === true
     */
    public function contentVuurwerk(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentWapen`-veld zichtbaarheid.
     *  - OF-rule f5363d0b-b344-4350-86c4-063b2ea97516 → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44') === true
     */
    public function contentWapen(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `contentZeppelin`-veld zichtbaarheid.
     *  - OF-rule d138e53e-eb22-4c93-9ec5-daba437208c3 → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39') === true
     */
    public function contentZeppelin(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `douches`-veld zichtbaarheid.
     *  - OF-rule 3d9f1e6c-85a9-449d-91c5-ebef408dd538 → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A13') === true
     */
    public function douches(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A13') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `ehbo`-veld zichtbaarheid.
     *  - OF-rule dcd1e4b3-7706-48df-a08f-3ad84369d580 → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A14') === true
     */
    public function ehbo(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A14') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `etenBereidenOfVerkopen`-veld zichtbaarheid.
     *  - OF-rule e8e0f322-bd43-4e79-9a3b-be489189920b → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true
     */
    public function etenBereidenOfVerkopen(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `evenmentenInDeBuurtContent`-veld zichtbaarheid.
     *  - OF-rule 00876823-b3f3-44f6-a177-d355c84c0b12 → show wanneer: JsTruthy::of($s->get('evenementenInDeGemeente'))
     */
    public function evenmentenInDeBuurtContent(): ?bool
    {
        $s = $this->state;
        if ((JsTruthy::of($s->get('evenementenInDeGemeente')))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `extraAfval`-veld zichtbaarheid.
     *  - OF-rule 03a87183-48c3-4e5b-b6ec-287c4f3daf97 → show wanneer: $s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33') === true
     */
    public function extraAfval(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `geluidstorens`-veld zichtbaarheid.
     *  - OF-rule 615d524a-498d-4e30-8279-2dc41ec7d6ac → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true
     */
    public function geluidstorens(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `groteVoertuigen`-veld zichtbaarheid.
     *  - OF-rule 4a05099f-5ded-49b6-a0a6-fc1544b55c25 → show wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true
     *  - OF-rule 565bccec-1a7b-40f3-975f-0edf8402b461 → show wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true
     */
    public function groteVoertuigen(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true) || ($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `infoTekstVeiligheidsplan`-veld zichtbaarheid.
     *  - OF-rule f1202010-b8b7-45c0-8f31-756190313451 → show wanneer: ($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C')
     */
    public function infoTekstVeiligheidsplan(): ?bool
    {
        $s = $this->state;
        if ((($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C'))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `kansspelen`-veld zichtbaarheid.
     *  - OF-rule 9b066ee5-3e95-45a1-9864-c444f1508300 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true
     */
    public function kansspelen(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `loadUserInformation`-veld zichtbaarheid.
     *  - OF-rule f56a54dd-4af9-452f-8bbd-cee5fba3c79b → hide wanneer: $s->get('eventloketSession') !== '{}'
     */
    public function loadUserInformation(): ?bool
    {
        $s = $this->state;
        if (($s->get('eventloketSession') !== '{}')) {
            return true; // hide
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `locatieSOpKaart`-veld zichtbaarheid.
     *  - OF-rule faa5fae6-c19f-4a8b-b138-a7b98fa44b95 → show wanneer: $s->get('waarVindtHetEvenementPlaats.buiten') === true
     */
    public function locatieSOpKaart(): ?bool
    {
        $s = $this->state;
        if (($s->get('waarVindtHetEvenementPlaats.buiten') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `marktkramen`-veld zichtbaarheid.
     *  - OF-rule 6cda93b8-4b85-4e9b-bc0e-89c45329ddac → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true
     */
    public function marktkramen(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `meldingvraag1`-veld zichtbaarheid.
     *  - OF-rule 454a40c6-43c8-42cd-9d2f-6d2ace4fec53 → show wanneer: ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_1'))
     */
    public function meldingvraag1(): ?bool
    {
        $s = $this->state;
        if ((($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_1')))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `meldingvraag2`-veld zichtbaarheid.
     *  - OF-rule 172fe1ad-207f-429a-ace2-d2d07b4ea92a → show wanneer: ($s->get('meldingvraag1') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_2'))
     */
    public function meldingvraag2(): ?bool
    {
        $s = $this->state;
        if ((($s->get('meldingvraag1') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_2')))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `meldingvraag3`-veld zichtbaarheid.
     *  - OF-rule 4e042329-a992-45ae-998b-521ea980c55a → show wanneer: ($s->get('meldingvraag2') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_3'))
     */
    public function meldingvraag3(): ?bool
    {
        $s = $this->state;
        if ((($s->get('meldingvraag2') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_3')))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `meldingvraag4`-veld zichtbaarheid.
     *  - OF-rule c7431a0c-f315-4768-8372-8703629228b8 → show wanneer: ($s->get('meldingvraag3') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_4'))
     */
    public function meldingvraag4(): ?bool
    {
        $s = $this->state;
        if ((($s->get('meldingvraag3') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_4')))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `meldingvraag5`-veld zichtbaarheid.
     *  - OF-rule 63781392-9b7b-45e3-823d-5b039784882e → show wanneer: ($s->get('meldingvraag4') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_5'))
     */
    public function meldingvraag5(): ?bool
    {
        $s = $this->state;
        if ((($s->get('meldingvraag4') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_5')))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX`-veld zichtbaarheid.
     *  - OF-rule e8e0f322-bd43-4e79-9a3b-be489189920b → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true
     */
    public function metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2.locatieVanOvernachtenDoorPersoneelOrganisatie1`-veld zichtbaarheid.
     *  - OF-rule 7b13e485-188e-4b37-8a31-c310ed165109 → show wanneer: $s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers1') === 'Ja'
     */
    public function opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2_locatieVanOvernachtenDoorPersoneelOrganisatie1(): ?bool
    {
        $s = $this->state;
        if (($s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers1') === 'Ja')) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1`-veld zichtbaarheid.
     *  - OF-rule 32f9bd89-ac3d-4fa4-b89f-1b9a48b13efb → show wanneer: $s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers') === 'Ja'
     */
    public function opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1(): ?bool
    {
        $s = $this->state;
        if (($s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers') === 'Ja')) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `organisatieInformatie`-veld zichtbaarheid.
     *  - OF-rule ce043762-6d77-44dc-8e8c-cb605e9acdfa → show wanneer: JsTruthy::of($s->get('eventloketSession.kvk'))
     *  - OF-rule 1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a → hide wanneer: $s->get('eventloketSession.kvk') === ''
     */
    public function organisatieInformatie(): ?bool
    {
        $s = $this->state;
        if ((JsTruthy::of($s->get('eventloketSession.kvk')))) {
            return false; // show
        }
        if (($s->get('eventloketSession.kvk') === '')) {
            return true; // hide
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `overkappingen`-veld zichtbaarheid.
     *  - OF-rule bf2ee2f8-9ea4-49a2-b1ab-2295c3b7052b → show wanneer: $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A56') === true
     */
    public function overkappingen(): ?bool
    {
        $s = $this->state;
        if (($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A56') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `overnachtingen`-veld zichtbaarheid.
     *  - OF-rule b782fae6-2270-4f90-930a-af073989e0f9 → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A17') === true
     */
    public function overnachtingen(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A17') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `podia`-veld zichtbaarheid.
     *  - OF-rule 0c026fb1-e43c-4fa7-a33f-615efd68d3bb → show wanneer: $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A55') === true
     */
    public function podia(): ?bool
    {
        $s = $this->state;
        if (($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A55') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `risicoClassificatieContent`-veld zichtbaarheid.
     *  - OF-rule b4fefcd8-faae-4139-93e1-e4b8108d6376 → show wanneer: $s->get('risicoClassificatie') !== ''
     */
    public function risicoClassificatieContent(): ?bool
    {
        $s = $this->state;
        if (($s->get('risicoClassificatie') !== '')) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `tenten`-veld zichtbaarheid.
     *  - OF-rule 8aa421de-5ac8-4451-a646-ef94e82e0d00 → show wanneer: $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A54') === true
     */
    public function tenten(): ?bool
    {
        $s = $this->state;
        if (($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A54') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `toegangVoorHulpdienstenIsBeperkt`-veld zichtbaarheid.
     *  - OF-rule 2a01382c-1fd2-4aac-82c7-c5fc22a5a4bf → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A11') === true
     */
    public function toegangVoorHulpdienstenIsBeperkt(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A11') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `userSelectGemeente`-veld zichtbaarheid.
     *  - OF-rule e3992429-730a-4ed9-af3c-62ad897933fe → show wanneer: (is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) >= 2
     */
    public function userSelectGemeente(): ?bool
    {
        $s = $this->state;
        if (((is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) >= 2)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `veiligheidsplan`-veld zichtbaarheid.
     *  - OF-rule f1202010-b8b7-45c0-8f31-756190313451 → show wanneer: ($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C')
     */
    public function veiligheidsplan(): ?bool
    {
        $s = $this->state;
        if ((($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C'))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `verkeersregelaars`-veld zichtbaarheid.
     *  - OF-rule 2bbecc17-8f88-474d-9399-acb4cd509541 → show wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true
     */
    public function verkeersregelaars(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `verkooppuntenCashless`-veld zichtbaarheid.
     *  - OF-rule e21a3eae-6e0f-479e-84e7-122e3401aac4 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true
     */
    public function verkooppuntenCashless(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `verkooppuntenMuntenEnBonnen`-veld zichtbaarheid.
     *  - OF-rule e21a3eae-6e0f-479e-84e7-122e3401aac4 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true
     */
    public function verkooppuntenMuntenEnBonnen(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `verkooppuntenToegangsKaarten`-veld zichtbaarheid.
     *  - OF-rule c1117aff-045d-4bf9-80c3-0ad446282328 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true
     */
    public function verkooppuntenToegangsKaarten(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `versterkteMuziek`-veld zichtbaarheid.
     *  - OF-rule 7b285070-2c40-4d8f-9b18-d20dd745bbd4 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true
     */
    public function versterkteMuziek(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `vervoersmaatregelen`-veld zichtbaarheid.
     *  - OF-rule f494443a-ef9a-4cb0-a0ff-9f422e2c3d2c → show wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52') === true
     */
    public function vervoersmaatregelen(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `verzorgingVanKinderenJongerDan12Jaar`-veld zichtbaarheid.
     *  - OF-rule 79be7168-edd7-48db-af66-525fa6a5815a → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A15') === true
     */
    public function verzorgingVanKinderenJongerDan12Jaar(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A15') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `voorwerpen`-veld zichtbaarheid.
     *  - OF-rule 615d524a-498d-4e30-8279-2dc41ec7d6ac → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true
     *  - OF-rule 6cda93b8-4b85-4e9b-bc0e-89c45329ddac → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true
     *  - OF-rule acc04d68-e446-4c59-b8a5-d40ef246ee74 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true
     *  - OF-rule c1117aff-045d-4bf9-80c3-0ad446282328 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true
     *  - OF-rule e0d010cd-193d-4a26-8a01-89b185d5709e → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true
     *  - OF-rule e21a3eae-6e0f-479e-84e7-122e3401aac4 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true
     *  - OF-rule e9cf76d6-9eca-4d23-b546-f6f4a9c4d471 → show wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true
     */
    public function voorwerpen(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `wCs`-veld zichtbaarheid.
     *  - OF-rule 935dc38c-383c-4c3d-abe1-a741bfba4a32 → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A12') === true
     */
    public function wCs(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A12') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `waarschuwingGeenKvk`-veld zichtbaarheid.
     *  - OF-rule 1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a → show wanneer: $s->get('eventloketSession.kvk') === ''
     */
    public function waarschuwingGeenKvk(): ?bool
    {
        $s = $this->state;
        if (($s->get('eventloketSession.kvk') === '')) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc`-veld zichtbaarheid.
     *  - OF-rule 21e363f3-9ca8-42d4-b52e-bddfab43ddd6 → show wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true
     */
    public function watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `watVoorBouwselsPlaatsUOpDeLocaties`-veld zichtbaarheid.
     *  - OF-rule 8e1a11b9-59f2-407b-8fb1-0fbee9712c08 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true
     */
    public function watVoorBouwselsPlaatsUOpDeLocaties(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `wegOfVaarwegAfsluiten`-veld zichtbaarheid.
     *  - OF-rule 2e67feb4-08d6-46f8-ab24-3ee91a387cb7 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A10') === true
     */
    public function wegOfVaarwegAfsluiten(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A10') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement`-veld zichtbaarheid.
     *  - OF-rule 0a5531ff-5f95-42e3-b911-53affa4c88d6 → show wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45') === true
     */
    public function welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement(): ?bool
    {
        $s = $this->state;
        if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX`-veld zichtbaarheid.
     *  - OF-rule 7b285070-2c40-4d8f-9b18-d20dd745bbd4 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true
     */
    public function welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning`-veld zichtbaarheid.
     *  - OF-rule 7b285070-2c40-4d8f-9b18-d20dd745bbd4 → show wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true
     */
    public function wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning(): ?bool
    {
        $s = $this->state;
        if (($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true)) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }

    /**
     * `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer`-veld zichtbaarheid.
     *  - OF-rule a757ea1f-24ee-40b8-a839-4e9997a33959 → show wanneer: $s->get('meldingsvraag5') === 'Ja'
     *  - OF-rule ceac4877-e22f-4d59-afac-cf2f29cb93d9 → show wanneer: ($s->get('meldingvraag4') === 'Ja') && ((array_values(array_filter([
                0 => 'gemeenteVariabelen.report_question_5',
            ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
     *  - OF-rule 981e2b88-49b3-4096-ae1d-07a4500e7ccc → show wanneer: ($s->get('meldingvraag2') === 'Ja') && ((array_values(array_filter([
                0 => 'gemeenteVariabelen.report_question_3',
            ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
     *  - OF-rule b741d925-75bf-4b8f-a0aa-47cdb0e5341d → show wanneer: ($s->get('meldingvraag3') === 'Ja') && ((array_values(array_filter([
                0 => 'gemeenteVariabelen.report_question_4',
            ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
     *  - OF-rule ea096e0f-e793-4df7-8292-df26ad862dc9 → show wanneer: ($s->get('meldingvraag1') === 'Ja') && ((array_values(array_filter([
                0 => 'gemeenteVariabelen.report_question_2',
            ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
     *  - OF-rule a64ed84a-d0a3-4560-b782-a24be41b3e4a → show wanneer: ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && ((array_values(array_filter([
                0 => 'gemeenteVariabelen.report_question_1',
            ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
     */
    public function wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer(): ?bool
    {
        $s = $this->state;
        if (($s->get('meldingsvraag5') === 'Ja') || (($s->get('meldingvraag4') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_5',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)) || (($s->get('meldingvraag2') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_3',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)) || (($s->get('meldingvraag3') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_4',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)) || (($s->get('meldingvraag1') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_2',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)) || (($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_1',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false))) {
            return false; // show
        }

        return null; // door-fall: default visibility uit step-file
    }
}
