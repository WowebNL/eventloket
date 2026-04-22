<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider: registratie-backend routing per gemeente en aanvraagsoort.
 *
 * Geëxtraheerd uit de 45 getranspileerde rules die de Open Forms-configuratie
 * 1-op-1 overnemen. Als deze pivot-tabel wijzigt in OF (nieuwe gemeente,
 * andere backend-toewijzing), moeten we hier bijhouden — de tests én het
 * rapport werken met exact deze data als waarheid.
 */
final class RegistrationBackendScenarios implements ScenarioProvider
{
    public static function categorie(): string
    {
        return 'routing';
    }

    public static function kop(): string
    {
        return 'Registratie-backend per gemeente en aanvraagsoort';
    }

    public static function inleiding(): string
    {
        return 'Elke nieuwe zaak wordt gerouteerd naar één van 45 registratie-backends. '
            .'Welke backend krijgt een zaak hangt af van twee dingen: de gemeente waar het '
            .'evenement plaatsvindt (herkend via de BRK-code) en de aanvraagsoort die de '
            .'organisator kiest (vergunning, vooraankondiging, of melding). '
            ."\n\n"
            .'15 deelnemende gemeentes × 3 aanvraagsoorten = 45 combinaties. Elke afwijking '
            .'hier betekent dat zaken in het verkeerde doel-systeem terechtkomen — dus moet '
            .'elke combinatie exact matchen met de OF-configuratie.';
    }

    /**
     * De pivot uit Open Forms (22 april 2026). Wijzig bij verandering in OF.
     *
     * @return array<string, array<string, string>>  brk → aanvraagsoort → backend
     */
    public static function pivot(): array
    {
        return [
            'GM0882' => ['vergunning' => 'backend23', 'vooraankondiging' => 'backend22', 'melding' => 'backend24'],
            'GM0888' => ['vergunning' => 'backend3',  'vooraankondiging' => 'backend9',  'melding' => 'backend8'],
            'GM0899' => ['vergunning' => 'backend15', 'vooraankondiging' => 'backend14', 'melding' => 'backend13'],
            'GM0917' => ['vergunning' => 'backend1',  'vooraankondiging' => 'backend4',  'melding' => 'backend6'],
            'GM0928' => ['vergunning' => 'backend21', 'vooraankondiging' => 'backend20', 'melding' => 'backend19'],
            'GM0938' => ['vergunning' => 'backend26', 'vooraankondiging' => 'backend25', 'melding' => 'backend27'],
            'GM0965' => ['vergunning' => 'backend29', 'vooraankondiging' => 'backend28', 'melding' => 'backend30'],
            'GM0971' => ['vergunning' => 'backend35', 'vooraankondiging' => 'backend34', 'melding' => 'backend36'],
            'GM0981' => ['vergunning' => 'backend38', 'vooraankondiging' => 'backend37', 'melding' => 'backend39'],
            'GM0986' => ['vergunning' => 'backend44', 'vooraankondiging' => 'backend43', 'melding' => 'backend45'],
            'GM0994' => ['vergunning' => 'backend41', 'vooraankondiging' => 'backend40', 'melding' => 'backend42'],
            'GM1729' => ['vergunning' => 'backend2',  'vooraankondiging' => 'backend5',  'melding' => 'backend7'],
            'GM1883' => ['vergunning' => 'backend32', 'vooraankondiging' => 'backend31', 'melding' => 'backend33'],
            'GM1903' => ['vergunning' => 'backend18', 'vooraankondiging' => 'backend17', 'melding' => 'backend16'],
            'GM1954' => ['vergunning' => 'backend10', 'vooraankondiging' => 'backend12', 'melding' => 'backend11'],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        $triggerBySoort = [
            'vergunning' => ['isVergunningaanvraag' => true],
            'vooraankondiging' => ['waarvoorWiltUEventloketGebruiken' => 'vooraankondiging'],
            'melding' => ['wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee'],
        ];
        $soortOmschrijving = [
            'vergunning' => 'vergunningaanvraag (volledige evenementenvergunning)',
            'vooraankondiging' => 'vooraankondiging (alleen aankondiging, nog geen vergunning)',
            'melding' => 'melding (lichter regime, geen wegafsluiting)',
        ];

        $scenarios = [];
        foreach (self::pivot() as $gemeente => $backends) {
            foreach ($backends as $soort => $backend) {
                $label = "{$gemeente} + {$soort} → {$backend}";
                $scenarios[$label] = [[
                    'naam' => $label,
                    'omschrijving' => "Voor gemeente {$gemeente} bij een {$soortOmschrijving[$soort]} "
                        ."moet het systeem de zaak naar registratie-backend '{$backend}' routeren.",
                    'categorie' => 'routing',
                    'stap' => null,
                    'trigger_velden' => array_merge(
                        ['evenementInGemeente.brk_identification'],
                        array_keys($triggerBySoort[$soort]),
                    ),
                    'gegeven' => array_merge(
                        ['evenementInGemeente.brk_identification' => $gemeente],
                        $triggerBySoort[$soort],
                    ),
                    'verwacht' => [
                        'system.registration_backend' => $backend,
                    ],
                ]];
            }
        }

        return $scenarios;
    }
}
