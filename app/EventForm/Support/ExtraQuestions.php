<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;

/**
 * Leest de per-gemeente ingestelde "Aanvullende vragen" uit de FormState.
 *
 * De lijst zelf wordt door `MunicipalityVariablesService` onder
 * `gemeenteVariabelen.extra_questions` gezet en gaat mee in de
 * `form_state_snapshot` van de zaak — bij het indienen is 'ie dus bevroren.
 *
 * Deze class is de enige plek die het padfilter uitvoert. Zowel de wizardstap
 * (`AanvullendeVragenStep`) als de rapportage (`SubmissionReport`) gebruiken
 * 'm, zodat een antwoord op een vraag die niet meer op het huidige pad van
 * toepassing is nergens opduikt — ook niet in de PDF, want die kijkt niet
 * naar `hidden`.
 *
 * @phpstan-type ExtraQuestion array{id: int|string, order?: int, type: string, label: string, helper_text?: string|null, options?: array<int, string>, is_required?: bool, show_for_aanvraag_types?: array<int, string>|null}
 */
final class ExtraQuestions
{
    /**
     * Voorvoegsel van de veldsleutels in de FormState (`extraVraag_12`).
     * Komt nergens anders in het formulier voor, dus geen botsing met een
     * bestaande veld- of variabelenaam.
     */
    public const FIELD_PREFIX = 'extraVraag_';

    /**
     * De actieve vragen die op het huidige aanvraagpad van toepassing zijn,
     * in ingestelde volgorde.
     *
     * @return list<array<string, mixed>>
     */
    public static function forState(FormState $state): array
    {
        $raw = $state->get('gemeenteVariabelen.extra_questions');
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        // Pas opvragen wanneer een vraag daadwerkelijk een padfilter heeft:
        // de bepaling loopt over de hele state en de meeste gemeenten
        // filteren niet.
        $aanvraagType = null;
        $questions = [];

        foreach ($raw as $question) {
            if (! is_array($question) || ! isset($question['id'], $question['type'])) {
                continue;
            }

            $paths = self::pathsFor($question);
            if ($paths !== []) {
                $aanvraagType ??= self::currentAanvraagType($state);
                if (! in_array($aanvraagType, $paths, true)) {
                    continue;
                }
            }

            $questions[] = $question;
        }

        return $questions;
    }

    /**
     * Of er voor deze state minstens één aanvullende vraag te tonen is. Bepaalt
     * of de wizardstap überhaupt in het formulier verschijnt.
     */
    public static function hasAny(FormState $state): bool
    {
        return self::forState($state) !== [];
    }

    /**
     * De veldsleutel waaronder het antwoord op deze vraag in de state staat.
     *
     * @param  array<string, mixed>  $question
     */
    public static function fieldKey(array $question): string
    {
        return self::FIELD_PREFIX.((string) ($question['id'] ?? ''));
    }

    /**
     * De aanvraagpaden waarvoor deze vraag geldt. Een lege lijst betekent:
     * geldt voor ieder pad.
     *
     * @param  array<string, mixed>  $question
     * @return list<string>
     */
    private static function pathsFor(array $question): array
    {
        $paths = $question['show_for_aanvraag_types'] ?? null;
        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_map(
            fn ($path): string => (string) $path,
            array_filter($paths, fn ($path): bool => is_string($path) && $path !== ''),
        ));
    }

    /**
     * Het huidige aanvraagpad als string. `DetermineAanvraagType` is de enige
     * plek die alle drie de paden kent; door 'm hier af te vangen is dit ook
     * het enige punt dat aangepast hoeft te worden mocht die klasse ooit een
     * enum in plaats van een string teruggeven.
     */
    private static function currentAanvraagType(FormState $state): string
    {
        return app(DetermineAanvraagType::class)->forState($state);
    }
}
