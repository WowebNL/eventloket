<?php

declare(strict_types=1);

namespace App\Console\Commands\EventForm;

use App\EventForm\Schema\EventFormSchema;
use App\Models\Zaak;
use Filament\Forms\Components\FileUpload;
use Illuminate\Console\Command;
use ReflectionObject;
use Throwable;
use Woweb\Openzaak\ObjectsApi;

/**
 * Eenmalige backfill: zet de OpenForms-submission die voor "oude" (vóór de
 * Filament-flow ingediende) zaken nog in de Objects API staat, om naar een
 * `form_state_snapshot`. Daarna lopen prefill ("herhaal aanvraag"), PDF en
 * samenvatting voor die zaken via het standaard-snapshotpad en is de Objects
 * API voor hen niet meer nodig.
 *
 * Bron-structuur: het Objects-record bevat onder `record.data.data` de
 * submission, diep genest — secties (kebab-case) → soms container-fieldsets
 * (`route`, `adresgegevens`, …) → de eigenlijke velden. De Objects API bevat
 * bovendien submissions van twee formulier-generaties; de meeste OF-keys
 * matchen de FormState-keys, maar een handvol is hernoemd (zie
 * LEGACY_KEY_MAP). `fetchSubmissionValues` lost beide op: het loopt recursief
 * door de boom, pakt élke key die het huidige formulier kent, en mapt de
 * legacy-keys. Onbekende/onzekere velden (en OF-bijlage-URL-objecten) vallen
 * weg — voor een prefill acceptabel: de organisator vult die opnieuw in.
 *
 * Bewust een command en geen migration: het doet per zaak een externe Objects-
 * API-call. Dat hoort niet in `artisan migrate` (breekt de deploy als de API
 * weg/onbereikbaar is). Het command is idempotent (alleen zaken zónder
 * snapshot) en herhaalbaar.
 *
 * Verifieer eerst met `--dry-run --zaak=<id>`: dat toont de omgezette snapshot
 * + welke keys het huidige formulier herkent en welke worden overgeslagen,
 * zonder iets op te slaan.
 */
final class BackfillSnapshotsFromObjects extends Command
{
    protected $signature = 'eventform:backfill-snapshots-from-objects
        {--zaak= : Beperk tot één zaak (id)}
        {--limit= : Maximaal aantal zaken verwerken}
        {--dry-run : Toon het resultaat zonder op te slaan}';

    protected $description = 'Zet de Objects-API-submission van oude zaken om naar form_state_snapshot.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $bekendeKeys = $this->knownFormKeys();

        $query = Zaak::query()
            ->whereNotNull('data_object_url')
            ->where(function ($q): void {
                // "Geen snapshot" = kolom is null of een lege JSON-array/object.
                $q->whereNull('form_state_snapshot')
                    ->orWhere('form_state_snapshot', '[]')
                    ->orWhere('form_state_snapshot', '{}');
            });

        if ($zaakId = $this->option('zaak')) {
            $query->where('id', $zaakId);
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $zaken = $query->get();

        if ($zaken->isEmpty()) {
            $this->info('Geen oude zaken zonder form_state_snapshot gevonden.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d zaak(en) te verwerken%s.', $zaken->count(), $dryRun ? ' (dry-run)' : ''));

        $verwerkt = 0;
        $overgeslagen = 0;
        $fouten = 0;

        foreach ($zaken as $zaak) {
            try {
                $values = $this->fetchSubmissionValues($zaak, $bekendeKeys);
            } catch (Throwable $e) {
                $this->error(sprintf('Zaak %s: ophalen Objects-record mislukt — %s', $zaak->public_id ?? $zaak->id, $e->getMessage()));
                $fouten++;

                continue;
            }

            if ($values === null) {
                $this->warn(sprintf('Zaak %s: Objects-record heeft geen record.data.data — overgeslagen.', $zaak->public_id ?? $zaak->id));
                $overgeslagen++;

                continue;
            }

            $this->reportZaak($zaak, $values, $bekendeKeys);

            if (! $dryRun) {
                $zaak->update(['form_state_snapshot' => ['values' => $values]]);
            }
            $verwerkt++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d verwerkt, %d overgeslagen, %d fouten.',
            $dryRun ? 'Dry-run klaar' : 'Klaar',
            $verwerkt,
            $overgeslagen,
            $fouten,
        ));

        return $fouten > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Legacy OF-veldnamen die in het nieuwe Filament-formulier hernoemd zijn.
     * De Objects API bevat submissions van twee formulier-generaties; de
     * oude generatie gebruikt deze keys, het nieuwe formulier de waarde.
     * Alleen 1-op-1 zekere hernoemingen — onzekere/samengestelde velden
     * (bv. aantal-aanwezigen-varianten, publieke-toegankelijkheid) staan
     * hier bewust NIET in en gaan bij prefill verloren (de organisator vult
     * die opnieuw in).
     *
     * @var array<string, string>
     */
    private const LEGACY_KEY_MAP = [
        'watIsDeNaamVanHetEvenement' => 'watIsDeNaamVanHetEvenementVergunning',
        'watIsDeStarttijdVanHetEvenement' => 'EvenementStart',
        'wanneerIsHetEindeVanHetEvenement' => 'EvenementEind',
        'voornaamIngelogdePersoon' => 'watIsUwVoornaam',
        'achternaamIngelogdePersoon' => 'watIsUwAchternaam',
    ];

    /**
     * Haal het Objects-record op en bouw een `{ FormState-key: waarde }`-map.
     *
     * De OF-submission is diep genest: secties (kebab-case) → soms nog
     * container-fieldsets (`route`, `adresgegevens`, `kolommen`, …) → de
     * eigenlijke velden. Plat-slaan op één niveau (de oude aanpak) miste
     * daardoor alle velden binnen die containers. We lopen daarom recursief
     * door de hele boom en pakken élke dict-key die het huidige formulier
     * kent. Dat filtert vanzelf de container-keys, geojson-internals
     * (`type`/`coordinates`) en checkbox-opties weg, en haalt geneste velden
     * als `route.routesOpKaart` of `adresgegevens.kolommen.0.<veld>` naar
     * boven. Legacy-hernoemingen worden via LEGACY_KEY_MAP omgezet.
     *
     * @param  array<string, true>  $bekendeKeys
     * @return array<string, mixed>|null null als er geen submission in zit
     */
    private function fetchSubmissionValues(Zaak $zaak, array $bekendeKeys): ?array
    {
        $uuid = $this->uuidFromUrl((string) $zaak->data_object_url);
        if ($uuid === '') {
            return null;
        }

        $object = (new ObjectsApi)->get($uuid)->toArray();
        $data = data_get($object, 'record.data.data');
        if (! is_array($data) || $data === []) {
            return null;
        }

        $values = [];
        $this->collectKnownKeys($data, $bekendeKeys, $values);

        // Map-velden: de OF-data heeft de oude Repeater-shape
        // (`[{innerKey: {Polygon|LineString}}]`), maar het nieuwe Map-veld
        // leest `state.geojson.features`. Zonder transform blijft de kaart
        // bij prefill leeg. Zet om naar de canonieke veld-shape.
        foreach (self::MAP_FIELDS as $mapKey) {
            if (isset($values[$mapKey])) {
                $values[$mapKey] = $this->toMapState($values[$mapKey]);
            }
        }

        return $values;
    }

    /**
     * Map-veld-keys waarvan de waarde een geometrie-tekening is. De OF-data
     * levert die in de oude geneste Repeater-shape aan; het nieuwe Map-veld
     * verwacht `{lat, lng, zoom, geojson: FeatureCollection}`.
     *
     * @var list<string>
     */
    private const MAP_FIELDS = ['locatieSOpKaart', 'routesOpKaart'];

    /**
     * Zet een oude OF-kaart-waarde om naar de Map-veld-shape die de
     * dotswan-component bij prefill leest: `{geojson: {type: FeatureCollection,
     * features: [...]}}`. Verzamelt recursief elke ruwe GeoJSON-geometrie
     * (Polygon/LineString/etc.) en wrapt 'm als Feature. Is de waarde al een
     * `{geojson: ...}`-object (nieuwe shape), dan blijft 'ie ongemoeid.
     */
    private function toMapState(mixed $value): mixed
    {
        if (is_array($value) && isset($value['geojson'])) {
            return $value;
        }

        $features = [];
        $this->collectGeometries($value, $features);
        if ($features === []) {
            return $value;
        }

        return ['geojson' => ['type' => 'FeatureCollection', 'features' => $features]];
    }

    /**
     * @param  list<array<string, mixed>>  $out
     */
    private function collectGeometries(mixed $node, array &$out): void
    {
        if (! is_array($node)) {
            return;
        }
        // Een ruwe geometrie: heeft `type` + `coordinates`.
        $type = $node['type'] ?? null;
        if (is_string($type) && isset($node['coordinates'])
            && in_array($type, ['Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon'], strict: true)
        ) {
            $out[] = ['type' => 'Feature', 'properties' => new \stdClass, 'geometry' => $node];

            return;
        }
        foreach ($node as $child) {
            $this->collectGeometries($child, $out);
        }
    }

    /**
     * Loop recursief door de OF-data en verzamel waarden voor elke key die
     * het formulier kent (direct of via LEGACY_KEY_MAP). Bij dubbele keys
     * wint de laatst-gevonden waarde — acceptabel voor een prefill.
     *
     * @param  array<string, true>  $bekendeKeys
     * @param  array<string, mixed>  $out
     */
    private function collectKnownKeys(mixed $node, array $bekendeKeys, array &$out): void
    {
        if (! is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            $target = self::LEGACY_KEY_MAP[$key] ?? (is_string($key) && isset($bekendeKeys[$key]) ? $key : null);
            if ($target !== null && $value !== null && $value !== '' && $value !== [] && $value !== ['']) {
                $out[$target] = $value;
            }
            // Ook bij een match doordalen: een container kan zowel zelf een
            // bekende key zijn als geneste bekende velden bevatten.
            $this->collectKnownKeys($value, $bekendeKeys, $out);
        }
    }

    private function uuidFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return trim(basename($path));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, true>  $bekendeKeys
     */
    private function reportZaak(Zaak $zaak, array $values, array $bekendeKeys): void
    {
        $this->newLine();
        $this->line(sprintf('<info>Zaak %s</info> (%d veld-keys):', $zaak->public_id ?? $zaak->id, count($values)));

        $herkend = [];
        $onbekend = [];
        foreach (array_keys($values) as $key) {
            if (isset($bekendeKeys[(string) $key])) {
                $herkend[] = (string) $key;
            } else {
                $onbekend[] = (string) $key;
            }
        }

        $this->line(sprintf('  <info>herkend door formulier (%d):</info> %s', count($herkend), implode(', ', $herkend) ?: '—'));
        if ($onbekend !== []) {
            $this->line(sprintf('  <comment>niet-herkend, overgeslagen bij prefill (%d):</comment> %s', count($onbekend), implode(', ', $onbekend)));
        }
    }

    /**
     * Alle veld-keys die het huidige formulier kent, door EventFormSchema te
     * walken. Hiermee laat de dry-run zien welke oude OF-keys het nieuwe
     * formulier daadwerkelijk herkent (en welke bij prefill wegvallen) —
     * los van een eventueel ontbrekende veldenkaart-dump.
     *
     * FileUpload-velden worden bewust overgeslagen: de bijbehorende
     * bestanden leven niet in de OF-submission maar in OpenZaak's
     * Documenten-API (gekoppeld via zaakinformatieobjecten; `bestandsnaam`
     * matcht OF's `originalName`). Ze worden al gelezen via
     * `Zaak::documenten` en horen dus niet in de snapshot. Zonder deze
     * uitsluiting zou de backfill het OF-`{url,name,size}`-object (een dode
     * submission-URL) als veldwaarde opslaan.
     *
     * @return array<string, true>
     */
    private function knownFormKeys(): array
    {
        $keys = [];
        $walk = function (object $component) use (&$walk, &$keys): void {
            if ($component instanceof FileUpload) {
                return;
            }
            if (method_exists($component, 'getName')) {
                $name = (string) $component->getName();
                if ($name !== '') {
                    $keys[$name] = true;
                }
            }
            if (! property_exists($component, 'childComponents')) {
                return;
            }
            $reflection = new ReflectionObject($component);
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
                        $walk($child);
                    }
                }
            }
        };

        foreach (EventFormSchema::steps() as $step) {
            $walk($step);
        }

        return $keys;
    }
}
