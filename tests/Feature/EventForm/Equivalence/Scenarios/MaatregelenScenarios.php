<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 13: Vergunningaanvraag: maatregelen.
 *
 * Hier vinkt de organisator aan welke maatregelen getroffen worden
 * (straatmeubilair verwijderen, extra afvalvoorzieningen, schoonmaak, etc.)
 * en voor elke aanvink verschijnt een detail-veld.
 */
final class MaatregelenScenarios implements ScenarioProvider
{
    public const STAP_MAATREGELEN = '8a5fb30f-287e-41a2-a9bc-e7340bdaaa99';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Detail-velden per aangevinkte maatregel';
    }

    public static function inleiding(): string
    {
        return 'Per aangevinkte overige maatregel (bijvoorbeeld "extra afval" of '
            .'"aanpassen straatmeubilair") verschijnt een detail-veld waarin de '
            .'organisator kan aangeven hoe dat wordt georganiseerd. Ook markeert '
            .'het systeem de maatregelen-pagina als van toepassing zodat ze in '
            .'de wizard-sidebar actief is.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Extra afvalvoorzieningen activeren detail-veld' => [[
                'naam' => 'Extra afval aangevinkt → detail-veld + maatregelen-stap actief',
                'omschrijving' => 'Als de organisator bij "kruis aan welke overige maatregelen" optie A33 '
                    .'(extra afvalvoorzieningen) aanvinkt, verschijnt het detail-veld waarin '
                    .'de aanpak beschreven kan worden, en wordt de maatregelen-pagina in de '
                    .'sidebar actief.',
                'categorie' => 'visibility',
                'stap' => self::STAP_MAATREGELEN,
                'trigger_velden' => ['kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX'],
                'gegeven' => [
                    'kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX' => ['A33' => true],
                ],
                'verwacht' => [
                    'field_hidden.extraAfval' => false,
                    'step_applicable.'.self::STAP_MAATREGELEN => true,
                ],
            ]],

            'Aanpassen straatmeubilair activeert detail-veld' => [[
                'naam' => 'Straatmeubilair aangevinkt → detail-veld + maatregelen-stap actief',
                'omschrijving' => 'Als de organisator kiest om straatmeubilair aan te passen of te verwijderen '
                    .'(optie A32), verschijnt het detail-veld waarin kan worden beschreven welke '
                    .'objecten verplaatst worden.',
                'categorie' => 'visibility',
                'stap' => self::STAP_MAATREGELEN,
                'trigger_velden' => ['kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX'],
                'gegeven' => [
                    'kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX' => ['A32' => true],
                ],
                'verwacht' => [
                    'field_hidden.aanpassenLocatieEnOfVerwijderenStraatmeubilair' => false,
                    'step_applicable.'.self::STAP_MAATREGELEN => true,
                ],
            ]],
        ];
    }
}
