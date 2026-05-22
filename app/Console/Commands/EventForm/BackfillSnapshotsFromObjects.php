<?php

declare(strict_types=1);

namespace App\Console\Commands\EventForm;

use App\EventForm\Schema\EventFormSchema;
use App\Models\Zaak;
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
 * Bron-structuur (bewezen door de oude `ViewZaak::prefil_new_request` op de
 * main-branch, die exact deze omzetting live deed): het Objects-record bevat
 * onder `record.data.data` de volledige submission, genest per stap-sectie.
 * Plat geslagen levert dat `{ <of-component-key>: <waarde> }` — en die keys
 * komen 1-op-1 overeen met de FormState-keys, omdat het nieuwe formulier uit
 * dezelfde OF-definitie is afgeleid.
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
                $values = $this->fetchSubmissionValues($zaak);
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
     * Haal het Objects-record op en sla `record.data.data` plat tot een
     * `{ key: value }`-map. Repliceert de bewezen ViewZaak-logica: secties
     * (geneste arrays van velden) worden gemerged; losse top-level velden
     * behouden hun key.
     *
     * @return array<string, mixed>|null null als er geen submission in zit
     */
    private function fetchSubmissionValues(Zaak $zaak): ?array
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
        foreach ($data as $key => $item) {
            if (is_array($item) && $this->isAssoc($item)) {
                // Stap-sectie: een associatieve map van veld → waarde.
                $values = array_merge($values, $item);

                continue;
            }
            // Los top-level veld (scalar of lijst): key behouden.
            $values[$key] = $item;
        }

        return $values;
    }

    private function uuidFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return trim(basename($path));
    }

    /**
     * @param  array<int|string, mixed>  $array
     */
    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
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
     * @return array<string, true>
     */
    private function knownFormKeys(): array
    {
        $keys = [];
        $walk = function (object $component) use (&$walk, &$keys): void {
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
