<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use App\EventForm\State\FormState;

/**
 * The three location kinds of `waarVindtHetEvenementPlaats` and the form
 * field that answers each of them.
 *
 * Only the field of a ticked kind is asked for, but unticking a kind does
 * not empty its field: Filament hides the component and keeps its raw
 * value, and `FormState::absorbFields()` merges rather than replaces, so
 * nothing ever removes it from the state. An application copied from an
 * earlier event therefore still carries the source event's address after
 * the organiser unticked "in a building" and drew a route instead.
 *
 * Everything that reads a location field must go through `valueFor()`, so
 * that leftover state of an unticked kind stays out of the result.
 */
final class LocationKinds
{
    /** Field key of the question that ticks the location kinds. */
    public const QUESTION = 'waarVindtHetEvenementPlaats';

    public const GEBOUW = 'gebouw';

    public const BUITEN = 'buiten';

    public const ROUTE = 'route';

    /**
     * The form field holding the answer for each location kind.
     *
     * @var array<string, string>
     */
    public const FIELD_BY_KIND = [
        self::GEBOUW => 'adresVanDeGebouwEn',
        self::BUITEN => 'locatieSOpKaart',
        self::ROUTE => 'routesOpKaart',
    ];

    /**
     * Is this location kind ticked?
     *
     * An unanswered question has no opinion: every kind counts. That covers
     * state built without the question (drafts from before it existed, a form
     * filled programmatically) as well as the empty list Filament gives a
     * checkbox list it has just filled. Nothing is lost by it: the question is
     * required, so the wizard does not let an empty answer past the location
     * step anyway.
     */
    public static function isSelected(FormState $state, string $kind): bool
    {
        $answer = $state->get(self::QUESTION);

        if (! is_array($answer) || $answer === []) {
            return true;
        }

        // `FormState::get()` normalises both shapes the answer occurs in:
        // Filament's list of ticked keys (`['gebouw', 'route']`) and the
        // object form the Open Formulieren rules used (`['gebouw' => true]`).
        return $state->get(self::QUESTION.'.'.$kind) === true;
    }

    /**
     * The value of the field belonging to $kind, or null when that kind is
     * not ticked (or when $kind is not a location kind at all).
     */
    public static function valueFor(FormState $state, string $kind): mixed
    {
        $field = self::FIELD_BY_KIND[$kind] ?? null;

        if ($field === null || ! self::isSelected($state, $kind)) {
            return null;
        }

        return $state->get($field);
    }

    /**
     * Does an unticked kind still hold an answer? That is the leftover state
     * described above: an address, area or route the organiser dropped but
     * that nothing removed. Anything derived from the location fields while
     * this is true was computed over more than the organiser is asking for.
     */
    public static function hasDroppedAnswers(FormState $state): bool
    {
        foreach (self::FIELD_BY_KIND as $kind => $field) {
            if (! self::isSelected($state, $kind) && ! empty($state->get($field))) {
                return true;
            }
        }

        return false;
    }
}
