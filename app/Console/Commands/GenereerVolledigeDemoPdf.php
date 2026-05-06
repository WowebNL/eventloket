<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventForm\Schema\EventFormSchema;
use App\EventForm\State\FormState;
use App\Jobs\Submit\GenerateSubmissionPdf;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Console\Command;
use ReflectionObject;

/**
 * Walkt het hele EventFormSchema en vult elk Field met een dummy-
 * waarde op basis van z'n type. Schrijft een Zaak + genereert de
 * inzendingsbewijs-PDF zodat je ziet hoe een MAXIMAAL ingevuld
 * formulier er in PDF uit ziet — inclusief alle Ja/Nee-radios,
 * alle CheckboxList-opties, alle TextInputs.
 *
 * Speciale shapes (locatieSOpKaart Map met polygons, routesOpKaart
 * Map met line, adresVanDeGebouwEn Repeater met AddressNL) worden
 * handmatig gevuld omdat ze meer dan de field-walk nodig hebben.
 */
class GenereerVolledigeDemoPdf extends Command
{
    protected $signature = 'eventform:genereer-volledige-demo-pdf';

    protected $description = 'Genereer een PDF op basis van een MAXIMAAL ingevuld formulier (elk veld dummy-vulling).';

    public function handle(): int
    {
        $values = $this->buildExhaustiveValues();
        $state = new FormState(values: $values);

        $zaak = Zaak::create([
            'public_id' => 'DEMO-FULL-'.substr(uniqid(), -6),
            'form_state_snapshot' => $state->toSnapshot(),
            'zaaktype_id' => Zaaktype::query()
                ->where('name', 'like', 'Evenementenvergunning%Heerlen%')
                ->first()?->id,
            'organisation_id' => Organisation::query()->first()?->id,
            'reference_data' => new ZaakReferenceData(
                start_evenement: '2026-08-15T16:00:00+02:00',
                eind_evenement: '2026-08-16T23:00:00+02:00',
                registratiedatum: now()->toIso8601String(),
                status_name: 'Ingediend',
                statustype_url: '',
                risico_classificatie: 'B',
                naam_locatie_eveneme: 'Theater Heerlen',
                naam_evenement: 'Stadsfestival Heerlen 2026',
                organisator: 'Stichting Stadsfestival Heerlen',
                aanwezigen: '5000',
                types_evenement: 'Festival',
                start_opbouw: '2026-08-14T08:00:00+02:00',
                eind_opbouw: '2026-08-15T15:30:00+02:00',
                start_afbouw: '2026-08-16T23:00:00+02:00',
                eind_afbouw: '2026-08-17T18:00:00+02:00',
            ),
        ]);

        (new GenerateSubmissionPdf($zaak))->handle();

        $path = sprintf('storage/app/private/zaken/%s/aanvraagformulier.pdf', $zaak->id);

        $this->info('Zaak: '.$zaak->public_id);
        $this->info('PDF: /var/www/html/'.$path);
        $this->info('Ingevulde velden in state: '.count($values));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExhaustiveValues(): array
    {
        $values = [];

        foreach (EventFormSchema::stepsForReport() as $step) {
            $this->walkAndFill($step, $values);
        }

        // Special-shapes die de naïeve walk niet aankan:

        // Locatie-keuze: alle drie aanvinken zodat alle locatie-blokken
        // zichtbaar zijn.
        $values['waarVindtHetEvenementPlaats'] = ['gebouw', 'buiten', 'route'];

        // Adres-Repeater met één rij + AddressNL onder fieldset-key.
        $values['adresVanDeGebouwEn'] = [[
            'naamVanDeLocatieGebouw' => 'Theater Heerlen',
            'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                'postcode' => '6411CD',
                'huisnummer' => '1',
                'huisletter' => '',
                'huisnummertoevoeging' => '',
                'straatnaam' => 'Markt',
                'plaatsnaam' => 'Heerlen',
            ],
        ]];

        // Map-state direct (post-LocatiePolygonsPatch shape).
        $values['naamVanDeLocatieKaart'] = 'Centrumplein + Marktplein';
        $values['locatieSOpKaart'] = [
            'lat' => 50.8867,
            'lng' => 5.9810,
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [
                    ['type' => 'Feature', 'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[5.9800, 50.8860], [5.9820, 50.8860], [5.9820, 50.8875], [5.9800, 50.8875], [5.9800, 50.8860]]],
                    ]],
                    ['type' => 'Feature', 'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[5.9830, 50.8865], [5.9850, 50.8865], [5.9850, 50.8880], [5.9830, 50.8880], [5.9830, 50.8865]]],
                    ]],
                ],
            ],
        ];
        $values['naamVanDeRoute'] = 'Avondparade-route';
        $values['routesOpKaart'] = [
            'lat' => 50.8867,
            'lng' => 5.9810,
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [
                    ['type' => 'Feature', 'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[5.9790, 50.8855], [5.9805, 50.8865], [5.9820, 50.8870], [5.9835, 50.8880], [5.9850, 50.8885]]],
                    ],
                ],
            ],
        ];

        // Tijden hebben een specifieke ISO-format nodig
        $values['EvenementStart'] = '2026-08-15T16:00';
        $values['EvenementEind'] = '2026-08-16T23:00';
        $values['OpbouwStart'] = '2026-08-14T08:00';
        $values['OpbouwEind'] = '2026-08-15T15:30';
        $values['AfbouwStart'] = '2026-08-16T23:00';
        $values['AfbouwEind'] = '2026-08-17T18:00';

        // Afgeleide variabele (FormDerivedState rekent normaal op
        // `inGemeentenResponse`-fetch; voor demo expliciet zetten zodat
        // gemeente-naam in PDF-meta verschijnt).
        $values['risicoClassificatie'] = 'B';
        $values['inGemeentenResponse'] = [
            'all' => [
                'items' => [
                    ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                ],
                'object' => [
                    'GM0917' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                ],
                'within' => true,
            ],
        ];

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function walkAndFill(object $component, array &$values): void
    {
        if ($component instanceof Repeater) {
            // Repeaters worden specifiek gevuld in buildExhaustiveValues();
            // overslaan tijdens de generieke walk.
            return;
        }

        if ($component instanceof Field) {
            $key = $component->getName();
            if ($key !== null && $key !== '' && ! isset($values[$key])) {
                $value = $this->dummyFor($component);
                if ($value !== null) {
                    $this->setNested($values, $key, $value);
                }
            }
        }

        $reflection = new ReflectionObject($component);
        if (! $reflection->hasProperty('childComponents')) {
            return;
        }
        $prop = $reflection->getProperty('childComponents');
        $prop->setAccessible(true);
        $children = $prop->getValue($component);
        if (! is_array($children)) {
            return;
        }
        foreach ($children as $list) {
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $child) {
                if (is_object($child)) {
                    $this->walkAndFill($child, $values);
                }
            }
        }
    }

    private function dummyFor(Field $component): mixed
    {
        $key = (string) $component->getName();

        return match (true) {
            // E-mail-velden krijgen een geldig adres
            str_contains(strtolower($key), 'email') || str_contains(strtolower($key), 'mailadres') => 'eva@stadsfestival.nl',
            // Telefoon
            str_contains(strtolower($key), 'telefoon') || str_contains(strtolower($key), 'phone') => '06-12345678',
            // KvK
            str_contains(strtolower($key), 'kamervankoophandel') || str_contains(strtolower($key), 'kvk') => '12345678',
            // Postcode
            str_ends_with($key, 'postcode') || str_contains(strtolower($key), 'postcode') => '6411CD',
            // Huisnummer
            str_ends_with($key, 'huisnummer') => '1',
            $component instanceof DateTimePicker => '2026-08-15T16:00',
            $component instanceof DatePicker => '2026-08-15',
            $component instanceof CheckboxList => $this->firstNOptions($component, 3),
            $component instanceof Radio, $component instanceof Select => $this->firstOption($component),
            $component instanceof Textarea => sprintf('Voorbeeld-tekst voor %s. Lorem ipsum dolor sit amet.', $key),
            $component instanceof TextInput => $this->dummyTextFor($key),
            default => null,
        };
    }

    private function dummyTextFor(string $key): string
    {
        $lc = strtolower($key);
        if (str_contains($lc, 'voornaam')) {
            return 'Eva';
        }
        if (str_contains($lc, 'achternaam')) {
            return 'de Vries';
        }
        if (str_contains($lc, 'naam') && str_contains($lc, 'organisatie')) {
            return 'Stichting Stadsfestival Heerlen';
        }
        if (str_contains($lc, 'naam') && str_contains($lc, 'evenement')) {
            return 'Stadsfestival Heerlen 2026';
        }
        if (str_contains($lc, 'straat')) {
            return 'Markt';
        }
        if (str_contains($lc, 'plaats')) {
            return 'Heerlen';
        }
        if (str_contains($lc, 'aantal') || str_contains($lc, 'totaal')) {
            return '5000';
        }

        return sprintf('Voorbeeld %s', substr($key, 0, 30));
    }

    /** Telt elke Radio-keuze zodat we Ja/Nee afwisselen i.p.v. altijd 'Ja' kiezen. */
    private int $radioToggle = 0;

    private function firstOption(Field $component): ?string
    {
        $options = $this->extractOptions($component);
        if ($options === []) {
            return null;
        }
        $keys = array_keys($options);

        // Voor Ja/Nee-radios afwisselen zodat de PDF beide kanten toont.
        if (count($keys) === 2 && in_array('Ja', $keys, true) && in_array('Nee', $keys, true)) {
            return ($this->radioToggle++ % 2 === 0) ? 'Ja' : 'Nee';
        }

        return (string) $keys[0];
    }

    /**
     * @return list<string>
     */
    private function firstNOptions(Field $component, int $n): array
    {
        $options = $this->extractOptions($component);
        if ($options === []) {
            return [];
        }

        return array_map(fn ($k) => (string) $k, array_slice(array_keys($options), 0, $n));
    }

    /**
     * @return array<int|string, string>
     */
    private function extractOptions(Field $component): array
    {
        $reflection = new ReflectionObject($component);
        if (! $reflection->hasProperty('options')) {
            return [];
        }
        $prop = $reflection->getProperty('options');
        $prop->setAccessible(true);
        $raw = $prop->getValue($component);
        if (! is_array($raw)) {
            return [];
        }

        return $raw;
    }

    /**
     * Schrijf een dot-pad-key in een geneste array. Filament's
     * AddressNL gebruikt sub-keys als `adresVan....1.postcode` —
     * we willen `['adresVan...1' => ['postcode' => ...]]`.
     *
     * @param  array<string, mixed>  $values
     */
    private function setNested(array &$values, string $key, mixed $value): void
    {
        if (! str_contains($key, '.')) {
            $values[$key] = $value;

            return;
        }
        $parts = explode('.', $key);
        $ref = &$values;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $ref[$part] = $value;
            } else {
                if (! isset($ref[$part]) || ! is_array($ref[$part])) {
                    $ref[$part] = [];
                }
                $ref = &$ref[$part];
            }
        }
    }
}
