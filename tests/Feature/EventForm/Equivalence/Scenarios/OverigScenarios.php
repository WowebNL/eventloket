<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 15: Vergunningaanvraag: overig.
 *
 * Bundelt de rest van de specifieke kenmerken: grote voertuigen op de openbare
 * weg, parkeerontheffingen, aanstelling van verkeersregelaars, etc.
 */
final class OverigScenarios implements ScenarioProvider
{
    public const STAP_OVERIG = '8a5fb30f-287e-41a2-a9bc-e7340bdaaa99';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Detail-velden voor overige kenmerken';
    }

    public static function inleiding(): string
    {
        return 'Voor kenmerken als grote voertuigen op de openbare weg (A48 of A49) '
            .'verschijnt een detail-veld waarin de organisator specifieke afspraken '
            .'kan doorgeven. De pagina "overig" wordt automatisch van toepassing.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'Grote voertuigen op openbare weg — detail-veld zichtbaar' => [[
                'naam' => 'Plaatsen object op openbare weg (A48) → detail-veld verschijnt',
                'omschrijving' => 'Als de organisator aangeeft objecten op de openbare weg te plaatsen '
                    .'(kenmerk A48), moet het detail-veld "groteVoertuigen" zichtbaar worden '
                    .'om de aanvullende gegevens te kunnen invullen.',
                'categorie' => 'visibility',
                'stap' => self::STAP_OVERIG,
                'trigger_velden' => ['kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX'],
                'gegeven' => [
                    'kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX' => ['A48' => true],
                ],
                'verwacht' => [
                    'field_hidden.groteVoertuigen' => false,
                    'step_applicable.'.self::STAP_OVERIG => true,
                ],
            ]],

            'Parkeerontheffing — detail-veld zichtbaar' => [[
                'naam' => 'Parkeren grote voertuigen (A49) → detail-veld verschijnt',
                'omschrijving' => 'Bij de keuze om grote voertuigen te parkeren op de openbare weg (A49) '
                    .'verschijnt hetzelfde detail-veld voor aanvullende gegevens.',
                'categorie' => 'visibility',
                'stap' => self::STAP_OVERIG,
                'trigger_velden' => ['kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX'],
                'gegeven' => [
                    'kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX' => ['A49' => true],
                ],
                'verwacht' => [
                    'field_hidden.groteVoertuigen' => false,
                    'step_applicable.'.self::STAP_OVERIG => true,
                ],
            ]],
        ];
    }
}
