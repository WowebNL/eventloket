<?php

declare(strict_types=1);

namespace App\EventForm\State;

use App\EventForm\Transpiler\JsTruthy;
use App\EventForm\Transpiler\MapContext;

/**
 * Pure-functions-class voor stap-zichtbaarheid. Gegenereerd uit de
 * 144 transpiled rule-files via dev-scripts/generate-step-applicability.php.
 *
 * Per OF-stap één arm in de match-statement die `bool|null`
 * retourneert:
 *   - `true`  → stap is applicable
 *   - `false` → stap is niet applicable (sidebar doorgestreept,
 *               wizard skipt 'm)
 *   - `null`  → geen mening; FormState valt terug op default
 *               (alle stappen applicable) of op een bag-entry van
 *               de oude engine (zolang die nog draait)
 *
 * `FormState::isStepApplicable()` raadpleegt deze class eerst.
 *
 * Pure-functioneel = toggle-veilig: switch-back werkt automatisch
 * correct zonder dat we `resetStepApplicable()` nodig hebben.
 */
final class FormStepApplicability
{
    public function __construct(private readonly FormState $state) {}

    // === GEGENEREERD via dev-scripts/generate-step-applicability.php ===
    // Aantal stappen met applicability-rules: 10
    
    /** @var array<string, true> */
    public const COMPUTED_STEPS = [
        '5f986f16-6a3a-4066-9383-d71f09877f47' => true,
        '661aabb7-e927-4a75-8d95-0a665c5d83fe' => true,
        '6e285ace-f891-4324-b54e-639c1cfff9fa' => true,
        '8a5fb30f-287e-41a2-a9bc-e7340bdaaa99' => true,
        'ae44ab5b-c068-4ceb-b121-6e6907f78ef9' => true,
        'c75cc256-6729-4684-9f9b-ede6265b3e72' => true,
        'd790edb5-712a-4f83-87a8-1a86e4831455' => true,
        'd87c01ce-8387-43b0-a8c8-e6cf5abb6da1' => true,
        'e8f00982-ee47-4bec-bf31-a5c8d1b05e5e' => true,
        'f4e91db5-fd74-4eba-b818-96ed2cc07d84' => true,
    ];
    
    public function get(string $stepUuid): ?bool
    {
        $s = $this->state;
    
        return match ($stepUuid) {
            '5f986f16-6a3a-4066-9383-d71f09877f47' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 87482f34-1e1f-4853-b2da-312c9b2cebf0 → NOT applicable wanneer: ($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPl
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                if ((($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja')) || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            '661aabb7-e927-4a75-8d95-0a665c5d83fe' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 0c026fb1-e43c-4fa7-a33f-615efd68d3bb → applicable wanneer: $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A55') === true
                //   - 2a01382c-1fd2-4aac-82c7-c5fc22a5a4bf → applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A11') === true
                //   - 2e67feb4-08d6-46f8-ab24-3ee91a387cb7 → applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A10') === true
                //   - 3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6 → NOT applicable wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
                //   - 7b285070-2c40-4d8f-9b18-d20dd745bbd4 → applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true
                //   - 8893efa1-663a-4ad6-9184-46ae7cb2ebf7 → applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A8') === true
                //   - 8aa421de-5ac8-4451-a646-ef94e82e0d00 → applicable wanneer: $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A54') === true
                //   - 8e1a11b9-59f2-407b-8fb1-0fbee9712c08 → applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                //   - 9b066ee5-3e95-45a1-9864-c444f1508300 → applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true
                //   - b92d2e5a-3ff7-4b1d-91d4-f1ca827247f7 → applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true
                //   - bf2ee2f8-9ea4-49a2-b1ab-2295c3b7052b → applicable wanneer: $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A56') === true
                //   - d566bba6-452c-480c-9a12-fcee922d0002 → NOT applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.') === true
                //   - e8e0f322-bd43-4e79-9a3b-be489189920b → applicable wanneer: $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true
                if (($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A55') === true) || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A11') === true) || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A10') === true) || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true) || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A8') === true) || ($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A54') === true) || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true) || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true) || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true) || ($s->get('watVoorBouwselsPlaatsUOpDeLocaties.A56') === true) || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true)) {
                    return true; // applicable
                }
                if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging') || ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.') === true)) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            '6e285ace-f891-4324-b54e-639c1cfff9fa' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 0a5531ff-5f95-42e3-b911-53affa4c88d6 → NOT applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45') === true
                //   - 199313af-cc35-4409-8398-294c658ae03f → applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38') === true
                //   - 35501489-2e07-4d62-b5df-da1b4795d5e7 → applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37') === true
                //   - 3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6 → NOT applicable wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
                //   - 72e81725-03fc-4c6e-8218-603bc7f07ef8 → applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                //   - 945f1606-e086-4999-983b-8b9c83dab421 → applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42') === true
                //   - ad564ba5-b144-438a-9449-dda1800ecbd3 → applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41') === true
                //   - ad8eb74d-08d5-4813-9c00-a914f6618300 → applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43') === true
                //   - d138e53e-eb22-4c93-9ec5-daba437208c3 → applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39') === true
                //   - f5363d0b-b344-4350-86c4-063b2ea97516 → applicable wanneer: $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44') === true
                if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38') === true) || ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37') === true) || ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true) || ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42') === true) || ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41') === true) || ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43') === true) || ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39') === true) || ($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44') === true)) {
                    return true; // applicable
                }
                if (($s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45') === true) || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            '8a5fb30f-287e-41a2-a9bc-e7340bdaaa99' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 03a87183-48c3-4e5b-b6ec-287c4f3daf97 → applicable wanneer: $s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33') === true
                //   - 0ab47106-f334-492a-b676-a98ca88c2a64 → applicable wanneer: $s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32') === true
                //   - 3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6 → NOT applicable wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                if (($s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33') === true) || ($s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32') === true)) {
                    return true; // applicable
                }
                if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            'ae44ab5b-c068-4ceb-b121-6e6907f78ef9' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6 → NOT applicable wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            'c75cc256-6729-4684-9f9b-ede6265b3e72' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6 → NOT applicable wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            'd790edb5-712a-4f83-87a8-1a86e4831455' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 2d10885d-3e3a-4df1-a17b-d979668d2581 → applicable wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true
                //   - 3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6 → NOT applicable wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
                //   - 615d524a-498d-4e30-8279-2dc41ec7d6ac → applicable wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true
                //   - 6cda93b8-4b85-4e9b-bc0e-89c45329ddac → applicable wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                //   - acc04d68-e446-4c59-b8a5-d40ef246ee74 → applicable wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true
                //   - c1117aff-045d-4bf9-80c3-0ad446282328 → applicable wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true
                //   - e0d010cd-193d-4a26-8a01-89b185d5709e → applicable wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true
                //   - e21a3eae-6e0f-479e-84e7-122e3401aac4 → applicable wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true
                //   - e9cf76d6-9eca-4d23-b546-f6f4a9c4d471 → applicable wanneer: $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true
                if (($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true) || ($s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true)) {
                    return true; // applicable
                }
                if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            'd87c01ce-8387-43b0-a8c8-e6cf5abb6da1' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                if (($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            'e8f00982-ee47-4bec-bf31-a5c8d1b05e5e' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 2bbecc17-8f88-474d-9399-acb4cd509541 → applicable wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true
                //   - 3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6 → NOT applicable wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
                //   - 4a05099f-5ded-49b6-a0a6-fc1544b55c25 → applicable wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true
                //   - 565bccec-1a7b-40f3-975f-0edf8402b461 → applicable wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                //   - f494443a-ef9a-4cb0-a0ff-9f422e2c3d2c → applicable wanneer: $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52') === true
                if (($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true) || ($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true) || ($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true) || ($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52') === true)) {
                    return true; // applicable
                }
                if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            'f4e91db5-fd74-4eba-b818-96ed2cc07d84' => (function () use ($s): ?bool {
                // OF-rules:
                //   - 145ceec2-91c7-4e67-8195-2444d734ddfc → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A20') === true
                //   - 21e363f3-9ca8-42d4-b52e-bddfab43ddd6 → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true
                //   - 3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6 → NOT applicable wanneer: $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
                //   - 3d9f1e6c-85a9-449d-91c5-ebef408dd538 → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A13') === true
                //   - 79be7168-edd7-48db-af66-525fa6a5815a → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A15') === true
                //   - 889aed1d-d7bc-4a93-b5b6-00c01f812724 → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A21') === true
                //   - 8f418d89-637a-45a6-8092-c2242201a009 → NOT applicable wanneer: $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
                //   - 935dc38c-383c-4c3d-abe1-a741bfba4a32 → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A12') === true
                //   - b782fae6-2270-4f90-930a-af073989e0f9 → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A17') === true
                //   - d5681327-869c-4a3a-be73-88c973668af1 → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A53') === true
                //   - d8d28395-9e5e-4570-a4f3-129ad988ae8f → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A19') === true
                //   - dcd1e4b3-7706-48df-a08f-3ad84369d580 → applicable wanneer: $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A14') === true
                if (($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A20') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A13') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A15') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A21') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A12') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A17') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A53') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A19') === true) || ($s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A14') === true)) {
                    return true; // applicable
                }
                if (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') || ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')) {
                    return false; // not applicable
                }
                return null; // door-fall: default applicable
            })(),
            default => null,
        };
    }
}
