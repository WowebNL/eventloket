<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Contract voor een klasse die equivalentie-scenarios levert. Zowel de
 * Pest-testsuite (via dataset-provider) als de `eventform:gedrags-rapport`-
 * artisan-command lezen via dit contract.
 *
 * Scenario-shape (elk element uit `all()`):
 *
 *   [
 *     'naam'            => string,        // 1-regel titel (NL)
 *     'omschrijving'    => string,        // 1-3 zinnen uitleg (NL)
 *     'categorie'       => string,        // 'routing' | 'visibility' | 'computation' | 'services'
 *     'stap'            => ?string,       // step-UUID of null voor cross-cutting
 *     'trigger_velden'  => list<string>,  // veld-keys die het gedrag triggeren
 *     'gegeven'         => array,         // initiële FormState waarden (dot-notation toegestaan)
 *     'verwacht'        => array,         // key => verwachte waarde na rule-evaluatie
 *   ]
 */
interface ScenarioProvider
{
    /**
     * Categorie onder welke de scenarios van deze provider vallen in het
     * rapport. Zie scenario-shape 'categorie' voor de bekende waardes.
     */
    public static function categorie(): string;

    /**
     * Kopregel voor deze groep scenarios in het rapport.
     * Bijv. "Registratie-backend per gemeente en aanvraagsoort".
     */
    public static function kop(): string;

    /**
     * Korte (1-3 zinnen) uitleg onder de kop in het rapport. Mensentaal,
     * context: waarom is dit gedrag belangrijk?
     */
    public static function inleiding(): string;

    /**
     * Alle scenarios van deze provider.
     *
     * @return array<string, array<int, array<string, mixed>>>  naam → [scenario-shape]
     */
    public static function all(): array;
}
