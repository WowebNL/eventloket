<?php

use App\Enums\MunicipalityVariableType;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use Illuminate\Database\Migrations\Migration;

/**
 * Voert de standaard-set MunicipalityVariables in die het formulier
 * gebruikt voor placeholder-vervanging in labels (bv. "Is het aantal
 * aanwezigen minder dan {{ aanwezigen }} personen?").
 *
 * Volgt het ReportQuestion-patroon: er wordt een set "template"-rijen
 * met `municipality_id = NULL` aangemaakt die de MunicipalityObserver
 * bij `Municipality::created` automatisch kopieert naar nieuwe
 * gemeenten. Plus: voor BESTAANDE gemeenten die nog geen variabelen
 * hebben, doen we de kopie hier ook handmatig zodat het formulier
 * meteen werkt zonder dat de gemeentebeheerder elke variabele
 * handmatig moet invoeren.
 */
return new class extends Migration
{
    /**
     * @return list<array{name: string, key: string, type: MunicipalityVariableType, value: mixed}>
     */
    private function defaults(): array
    {
        return [
            [
                'name' => 'Maximaal aantal aanwezigen voor melding',
                'key' => 'aanwezigen',
                'type' => MunicipalityVariableType::Number,
                'value' => 500,
            ],
            [
                'name' => 'Maximaal aantal objecten zonder vergunning',
                'key' => 'aantal_objecten',
                'type' => MunicipalityVariableType::Number,
                'value' => 10,
            ],
            [
                'name' => 'Maximale grootte objecten (m²)',
                'key' => 'maximale_grootte_objecten_in_m2',
                'type' => MunicipalityVariableType::Number,
                'value' => 50,
            ],
            [
                'name' => 'Maximale dB(A) voor melding',
                'key' => 'melding_maximale_dba',
                'type' => MunicipalityVariableType::Number,
                'value' => 80,
            ],
            [
                'name' => 'Muziektijden',
                'key' => 'muziektijden',
                'type' => MunicipalityVariableType::TimeRange,
                'value' => ['start' => '09:00', 'end' => '22:00'],
            ],
            [
                'name' => 'Tijdstip mogelijk niet-vergunningsplichtig',
                'key' => 'tijdstip_mogelijk_niet_vergunningsplichtig',
                'type' => MunicipalityVariableType::TimeRange,
                'value' => ['start' => '09:00', 'end' => '22:00'],
            ],
            [
                'name' => 'Tekst bij alcohol-ontheffing',
                'key' => 'melding_alcohol_ontheffing_tekst',
                'type' => MunicipalityVariableType::Text,
                'value' => 'Voor het schenken van alcohol heeft u een ontheffing op grond van artikel 35 Drank- en Horecawet nodig.',
            ],
            [
                'name' => 'Tekst bij drone-opnames',
                'key' => 'melding_drone_ontheffing_tekst',
                'type' => MunicipalityVariableType::Text,
                'value' => 'Voor het maken van filmopnames met behulp van drones gelden landelijke regels van het ministerie I&W.',
            ],
        ];
    }

    public function up(): void
    {
        $defaults = $this->defaults();

        // 1. Template-rijen (`municipality_id = NULL`) zodat de
        //    MunicipalityObserver ze bij toekomstige gemeenten kopieert.
        foreach ($defaults as $entry) {
            MunicipalityVariable::firstOrCreate(
                ['municipality_id' => null, 'key' => $entry['key']],
                [
                    'name' => $entry['name'],
                    'type' => $entry['type'],
                    'value' => $entry['value'],
                    'is_default' => true,
                ],
            );
        }

        // 2. Voor elke BESTAANDE gemeente die nog geen MunicipalityVariables
        //    heeft, kopieer de defaults zodat het formulier meteen werkt.
        Municipality::all()->each(function (Municipality $municipality) use ($defaults): void {
            foreach ($defaults as $entry) {
                MunicipalityVariable::firstOrCreate(
                    ['municipality_id' => $municipality->id, 'key' => $entry['key']],
                    [
                        'name' => $entry['name'],
                        'type' => $entry['type'],
                        'value' => $entry['value'],
                        'is_default' => true,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        $keys = array_map(fn (array $d): string => $d['key'], $this->defaults());

        // Verwijder zowel template-rijen als de gekopieerde defaults.
        // Custom waarden die door gemeentebeheerders zijn aangepast:
        // alleen verwijderen als is_default=true blijft staan (= niet
        // door beheerder aangepast).
        MunicipalityVariable::whereIn('key', $keys)
            ->where('is_default', true)
            ->delete();
    }
};
