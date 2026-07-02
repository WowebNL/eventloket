<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Enums\BlueprintFindingType;
use App\Livewire\ConnectionVerifier;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\BlueprintFinding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Facades\Zgw;

/**
 * Checks a zaaktype's current definitief version against the blueprint
 * prerequisites Eventloket relies on (zgw-koppelingbeheer.md, section 4.4):
 * begin- and eindstatus, an initiator roltype, an "Ingetrokken" resultaattype,
 * informatieobjecttypen for the aanvraag-PDF and bijlagen, every eigenschap the
 * koppeling maps, and the intern_zaaknummer eigenschap the zaak flow requires.
 *
 * Child lists are read directly against the version url (not through the
 * cached {@see ZaaktypeCatalogusOptions}, which may still hold pre-publish
 * data). Transport errors skip the affected checks with a warning instead of
 * producing findings: a flaky read must not raise false alarms.
 *
 * Reuse hooks: the {@see ConnectionVerifier} modal and the
 * koppeling form could surface these findings interactively as well.
 */
final class ZaaktypeBlueprintHealth
{
    /**
     * @return list<BlueprintFinding>
     */
    public function check(string $connectionName, string $identificatie, ?MunicipalityZaaktypeMapping $mapping): array
    {
        try {
            $version = ZaaktypeVersion::currentDefinitief($connectionName, $identificatie);
        } catch (Throwable $e) {
            $this->logSkip('zaaktype version', $connectionName, $identificatie, $e);

            return [];
        }

        if ($version === null) {
            // Unavailability is a routing concern handled by the refresher, not
            // a blueprint finding.
            return [];
        }

        $url = $version['url'];

        return [
            ...$this->checkStatustypen($connectionName, $identificatie, $url, $mapping),
            ...$this->checkRoltypen($connectionName, $identificatie, $url, $mapping),
            ...$this->checkResultaattypen($connectionName, $identificatie, $url, $mapping),
            ...$this->checkInformatieobjecttypen($connectionName, $identificatie, $url, $mapping),
            ...$this->checkEigenschappen($connectionName, $identificatie, $url, $mapping),
        ];
    }

    /**
     * @return list<BlueprintFinding>
     */
    private function checkStatustypen(string $connectionName, string $identificatie, string $url, ?MunicipalityZaaktypeMapping $mapping): array
    {
        $statustypen = $this->index($connectionName, $identificatie, 'statustypen', $url);

        if ($statustypen === null) {
            return [];
        }

        $findings = [];

        if (ZaaktypeBlueprint::initialStatustype($mapping, $statustypen) === null) {
            $findings[] = new BlueprintFinding('initial_statustype', BlueprintFindingType::Missing);
        } elseif ($mapping?->initial_statustype && $statustypen->firstWhere('omschrijving', $mapping->initial_statustype) === null) {
            $findings[] = new BlueprintFinding('initial_statustype', BlueprintFindingType::MappedValueNotFound, $mapping->initial_statustype);
        }

        if (ZaaktypeBlueprint::eindStatustype($mapping, $statustypen) === null) {
            $findings[] = new BlueprintFinding('eind_statustype', BlueprintFindingType::Missing);
        } elseif ($mapping?->eind_statustype && $statustypen->firstWhere('omschrijving', $mapping->eind_statustype) === null) {
            $findings[] = new BlueprintFinding('eind_statustype', BlueprintFindingType::MappedValueNotFound, $mapping->eind_statustype);
        }

        return $findings;
    }

    /**
     * @return list<BlueprintFinding>
     */
    private function checkRoltypen(string $connectionName, string $identificatie, string $url, ?MunicipalityZaaktypeMapping $mapping): array
    {
        $roltypen = $this->index($connectionName, $identificatie, 'roltypen', $url);

        if ($roltypen === null) {
            return [];
        }

        if (ZaaktypeBlueprint::initiatorRoltype($mapping, $roltypen) === null) {
            return [new BlueprintFinding('initiator_roltype', BlueprintFindingType::Missing)];
        }

        $mapped = $mapping?->initiator_roltype;

        if ($mapped && $roltypen->first(fn ($r) => ($r['omschrijving'] ?? null) === $mapped || ($r['omschrijvingGeneriek'] ?? null) === $mapped) === null) {
            return [new BlueprintFinding('initiator_roltype', BlueprintFindingType::MappedValueNotFound, $mapped)];
        }

        return [];
    }

    /**
     * @return list<BlueprintFinding>
     */
    private function checkResultaattypen(string $connectionName, string $identificatie, string $url, ?MunicipalityZaaktypeMapping $mapping): array
    {
        $resultaattypen = $this->index($connectionName, $identificatie, 'resultaattypen', $url);

        if ($resultaattypen === null) {
            return [];
        }

        if (ZaaktypeBlueprint::ingetrokkenResultaattype($mapping, $resultaattypen) === null) {
            return [new BlueprintFinding('ingetrokken_resultaattype', BlueprintFindingType::Missing)];
        }

        $mapped = $mapping?->ingetrokken_resultaattype;

        if ($mapped && $resultaattypen->first(fn ($r) => ($r['omschrijving'] ?? null) === $mapped || ($r['omschrijvingGeneriek'] ?? null) === $mapped) === null) {
            return [new BlueprintFinding('ingetrokken_resultaattype', BlueprintFindingType::MappedValueNotFound, $mapped)];
        }

        return [];
    }

    /**
     * @return list<BlueprintFinding>
     */
    private function checkInformatieobjecttypen(string $connectionName, string $identificatie, string $url, ?MunicipalityZaaktypeMapping $mapping): array
    {
        $omschrijvingen = $this->informatieobjecttypeOmschrijvingen($connectionName, $identificatie, $url);

        if ($omschrijvingen === null) {
            return [];
        }

        if ($omschrijvingen->isEmpty()) {
            return [
                new BlueprintFinding('aanvraag_informatieobjecttype', BlueprintFindingType::Missing),
                new BlueprintFinding('bijlage_informatieobjecttype', BlueprintFindingType::Missing),
            ];
        }

        $findings = [];

        if ($mapping?->aanvraag_informatieobjecttype && ! $omschrijvingen->contains($mapping->aanvraag_informatieobjecttype)) {
            $findings[] = new BlueprintFinding('aanvraag_informatieobjecttype', BlueprintFindingType::MappedValueNotFound, $mapping->aanvraag_informatieobjecttype);
        }

        if ($mapping?->bijlage_informatieobjecttype && ! $omschrijvingen->contains($mapping->bijlage_informatieobjecttype)) {
            $findings[] = new BlueprintFinding('bijlage_informatieobjecttype', BlueprintFindingType::MappedValueNotFound, $mapping->bijlage_informatieobjecttype);
        }

        return $findings;
    }

    /**
     * @return list<BlueprintFinding>
     */
    private function checkEigenschappen(string $connectionName, string $identificatie, string $url, ?MunicipalityZaaktypeMapping $mapping): array
    {
        $eigenschappen = $this->index($connectionName, $identificatie, 'eigenschappen', $url);

        if ($eigenschappen === null) {
            return [];
        }

        $namen = $eigenschappen->pluck('naam')->filter()->values();
        $findings = [];

        foreach ($mapping->eigenschap_map ?? [] as $logicalKey => $naam) {
            if (is_string($naam) && $naam !== '' && ! $namen->contains($naam)) {
                $findings[] = new BlueprintFinding("eigenschap:{$logicalKey}", BlueprintFindingType::MappedValueNotFound, $naam);
            }
        }

        // The zaak flow always writes the internal zaaknummer eigenschap
        // (see SyncZaaktypeEigenschappen and AddZaakeigenschappenZGW).
        $internZaaknummer = ZaaktypeBlueprint::eigenschapNaam($mapping, 'intern_zaaknummer');

        if (! $namen->contains($internZaaknummer)) {
            $findings[] = new BlueprintFinding('eigenschap:intern_zaaknummer', BlueprintFindingType::Missing);
        }

        return $findings;
    }

    /**
     * Read a catalogus child list for the version url. Null means the read
     * failed and the caller must skip its checks.
     *
     * @return Collection<int, array<string, mixed>>|null
     */
    private function index(string $connectionName, string $identificatie, string $resource, string $url): ?Collection
    {
        try {
            return Zgw::connection($connectionName)->catalogi()->{$resource}()->index(['zaaktype' => $url])->collect();
        } catch (Throwable $e) {
            $this->logSkip($resource, $connectionName, $identificatie, $e);

            return null;
        }
    }

    /**
     * The omschrijvingen of the informatieobjecttypen linked to the version,
     * resolved through the standard zaaktype-informatieobjecttypen relation
     * (mirrors {@see Zaaktype::getDocumentTypes()} and
     * {@see ZaaktypeCatalogusOptions::informatieobjecttypen()}).
     *
     * @return Collection<int, string>|null
     */
    private function informatieobjecttypeOmschrijvingen(string $connectionName, string $identificatie, string $url): ?Collection
    {
        $relations = $this->index($connectionName, $identificatie, 'zaaktypeInformatieobjecttypen', $url);

        if ($relations === null) {
            return null;
        }

        $omschrijvingen = collect();

        foreach ($relations as $relation) {
            $value = $relation['informatieobjecttype'] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            // OpenZaak returns a URL to the informatieobjecttype; RX Mission
            // returns the omschrijving inline.
            if (str_starts_with($value, 'http')) {
                try {
                    $value = ZgwResource::byUrl($connectionName, $value)['omschrijving'] ?? null;
                } catch (Throwable $e) {
                    $this->logSkip('informatieobjecttype', $connectionName, $identificatie, $e);

                    continue;
                }
            }

            if (is_string($value) && $value !== '') {
                $omschrijvingen->push($value);
            }
        }

        return $omschrijvingen;
    }

    private function logSkip(string $resource, string $connectionName, string $identificatie, Throwable $e): void
    {
        Log::warning('ZaaktypeBlueprintHealth: skipped a check because the catalogus read failed.', [
            'resource' => $resource,
            'connection' => $connectionName,
            'identificatie' => $identificatie,
            'exception' => $e->getMessage(),
        ]);
    }
}
