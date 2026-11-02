<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;

/**
 * Reads the "Aanvullende vragen" configured per municipality from the FormState.
 *
 * The list itself is set by `MunicipalityVariablesService` under
 * `gemeenteVariabelen.extra_questions` and travels along in the
 * `form_state_snapshot` of the zaak, so it is frozen at submission.
 *
 * This class is the only place that applies the path filter. Both the wizard
 * step (`AanvullendeVragenStep`) and the report (`SubmissionReport`) use it,
 * so an answer to a question that no longer applies to the current path never
 * shows up anywhere, not even in the PDF, which does not look at `hidden`.
 *
 * @phpstan-type ExtraQuestion array{id: int|string, order?: int, type: string, label: string, helper_text?: string|null, options?: array<int, string>, is_required?: bool, show_for_aanvraag_types?: array<int, string>|null}
 */
final class ExtraQuestions
{
    /**
     * Prefix of the field keys in the FormState (`extraVraag_12`). It is used
     * nowhere else in the form, so it cannot clash with an existing field or
     * variable name.
     */
    public const FIELD_PREFIX = 'extraVraag_';

    /**
     * The active questions that apply to the current aanvraag path, in their
     * configured order.
     *
     * @return list<array<string, mixed>>
     */
    public static function forState(FormState $state): array
    {
        $raw = $state->get('gemeenteVariabelen.extra_questions');
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        // Only determined when a question actually has a path filter: the
        // determination walks the whole state and most municipalities do not
        // filter.
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
     * Whether this state has at least one extra question to show. Decides
     * whether the wizard step appears in the form at all.
     */
    public static function hasAny(FormState $state): bool
    {
        return self::forState($state) !== [];
    }

    /**
     * The field key under which the answer to this question is stored in the
     * state.
     *
     * @param  array<string, mixed>  $question
     */
    public static function fieldKey(array $question): string
    {
        return self::FIELD_PREFIX.((string) ($question['id'] ?? ''));
    }

    /**
     * Whether this question is asked on the given aanvraag path. A question
     * without a path filter is asked on every path.
     *
     * @param  array<string, mixed>  $question
     */
    public static function appliesTo(array $question, ZaaktypeRole $role): bool
    {
        $paths = self::pathsFor($question);

        return $paths === [] || in_array($role->value, $paths, true);
    }

    /**
     * The aanvraag paths this question applies to. An empty list means it
     * applies to every path.
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
     * The current aanvraag path as a string. `DetermineAanvraagType` returns
     * the `ZaaktypeRole`; the stored `show_for_aanvraag_types` are still plain
     * strings, so this is the single point where the enum is reduced to its
     * backing value.
     */
    private static function currentAanvraagType(FormState $state): string
    {
        return app(DetermineAanvraagType::class)->forState($state)->value;
    }
}
