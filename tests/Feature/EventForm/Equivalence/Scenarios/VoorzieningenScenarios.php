<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 11: Vergunningsaanvraag: voorzieningen.
 *
 * Op deze pagina kruist de organisator aan welke voorzieningen aanwezig zijn
 * (wc\'s, douches, water, stroom, EHBO-post, etc.). Voor elk aangekruiste
 * voorziening moet een detail-veld verschijnen. Dat loopt in OF via losse
 * rules per voorziening.
 */
final class VoorzieningenScenarios implements ScenarioProvider
{
    public const STAP_VOORZIENINGEN = 'f4e91db5-fd74-4eba-b818-96ed2cc07d84';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Detail-velden per aangevinkte voorziening';
    }

    public static function inleiding(): string
    {
        return 'Voor elke voorziening die de organisator aankruist (wc\'s, douches, etc.) '
            .'moet een detail-veld verschijnen waarin de organisator bijvoorbeeld het '
            .'aantal kan aangeven. Deze pagina wordt ook automatisch als van toepassing '
            .'gemarkeerd zodra minstens één voorziening is aangevinkt.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'WCs-detailveld zichtbaar na aanvinken van WCs' => [[
                'naam' => 'WCs aangevinkt → detailveld voor aantallen verschijnt',
                'omschrijving' =>
                    'Als de organisator bij de voorzieningen-checkboxen optie A12 (wc\'s) '
                    .'aanvinkt, wordt het detail-veld zichtbaar waarin de aantallen wc\'s kunnen '
                    .'worden ingevuld. De pagina voorzieningen zelf wordt als van toepassing '
                    .'gemarkeerd in de sidebar.',
                'categorie' => 'visibility',
                'stap' => self::STAP_VOORZIENINGEN,
                'trigger_velden' => ['welkeVoorzieningenZijnAanwezigBijUwEvenement'],
                'gegeven' => [
                    'welkeVoorzieningenZijnAanwezigBijUwEvenement' => ['A12' => true],
                ],
                'verwacht' => [
                    'field_hidden.wCs' => false,
                    'step_applicable.'.self::STAP_VOORZIENINGEN => true,
                ],
            ]],

            'Douches-detailveld zichtbaar na aanvinken' => [[
                'naam' => 'Douches aangevinkt → detailveld voor douches verschijnt',
                'omschrijving' =>
                    'Net als bij WCs: als de organisator douches (optie A13) aanvinkt in de '
                    .'voorzieningen-lijst, wordt het douches-detailveld zichtbaar zodat de '
                    .'organisator aantallen/locaties kan doorgeven.',
                'categorie' => 'visibility',
                'stap' => self::STAP_VOORZIENINGEN,
                'trigger_velden' => ['welkeVoorzieningenZijnAanwezigBijUwEvenement'],
                'gegeven' => [
                    'welkeVoorzieningenZijnAanwezigBijUwEvenement' => ['A13' => true],
                ],
                'verwacht' => [
                    'field_hidden.douches' => false,
                    'step_applicable.'.self::STAP_VOORZIENINGEN => true,
                ],
            ]],
        ];
    }
}
