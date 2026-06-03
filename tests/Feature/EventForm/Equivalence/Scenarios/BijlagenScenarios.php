<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 16: Bijlagen.
 *
 * Op de bijlagen-pagina verwacht het systeem bepaalde documenten afhankelijk
 * van eerder gemaakte keuzes. Bij een B- of C-classificatie moet er een
 * veiligheidsplan bijgevoegd worden; bij specifieke kenmerken komt er een
 * bebordings- en bewegwijzeringsplan bij.
 */
final class BijlagenScenarios implements ScenarioProvider
{
    public const STAP_BIJLAGEN = '7982e106-bce0-49cf-bdaa-ada9eac8b6ba';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Bijlage-upload-velden op basis van risico en kenmerken';
    }

    public static function inleiding(): string
    {
        return 'Welke bijlagen verplicht zijn hangt af van de risico-classificatie '
            .'en de kenmerken van het evenement. Deze scenarios tonen dat het '
            .'veiligheidsplan-veld verschijnt bij B- of C-classificatie, en dat '
            .'het bebordingsplan-veld verschijnt als kenmerk A50 is aangevinkt.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Veiligheidsplan verplicht bij classificatie B' => [[
                'naam' => 'Classificatie B → upload-veld veiligheidsplan verschijnt',
                'omschrijving' => 'Bij een middelhoog risico (classificatie B) is de organisator verplicht een '
                    .'veiligheidsplan te uploaden. Het veiligheidsplan-veld wordt zichtbaar, samen '
                    .'met de bijbehorende uitleg-teksten.',
                'categorie' => 'visibility',
                'stap' => self::STAP_BIJLAGEN,
                'trigger_velden' => ['risicoClassificatie'],
                'gegeven' => [
                    'risicoClassificatie' => 'B',
                ],
                'verwacht' => [
                    'field_hidden.veiligheidsplan' => false,
                    'field_hidden.infoTekstVeiligheidsplan' => false,
                ],
            ]],

            'Veiligheidsplan verplicht bij classificatie C' => [[
                'naam' => 'Classificatie C → upload-veld veiligheidsplan verschijnt',
                'omschrijving' => 'Hoog-risico evenementen (classificatie C) vragen om hetzelfde veiligheidsplan '
                    .'als B. Het upload-veld en de uitleg-tekst worden zichtbaar.',
                'categorie' => 'visibility',
                'stap' => self::STAP_BIJLAGEN,
                'trigger_velden' => ['risicoClassificatie'],
                'gegeven' => [
                    'risicoClassificatie' => 'C',
                ],
                'verwacht' => [
                    'field_hidden.veiligheidsplan' => false,
                    'field_hidden.infoTekstVeiligheidsplan' => false,
                ],
            ]],

            'Bebordingsplan verplicht bij kenmerk A50' => [[
                'naam' => 'Verkeersmaatregelen (A50) → upload-veld bebordingsplan verschijnt',
                'omschrijving' => 'Als de organisator aangeeft verkeersmaatregelen te treffen (kenmerk A50), '
                    .'moet er een bebordings- en bewegwijzeringsplan bijgevoegd worden. Het '
                    .'upload-veld daarvoor wordt zichtbaar.',
                'categorie' => 'visibility',
                'stap' => self::STAP_BIJLAGEN,
                'trigger_velden' => ['kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX'],
                'gegeven' => [
                    'kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX' => ['A50' => true],
                ],
                'verwacht' => [
                    'field_hidden.bebordingsEnBewegwijzeringsplan' => false,
                ],
            ]],
        ];
    }
}
