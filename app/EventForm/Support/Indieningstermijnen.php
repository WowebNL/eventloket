<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use App\EventForm\State\FormState;

/**
 * Single definition of the submission-deadline municipality variables and
 * of which of them apply to the path the form is currently on.
 *
 * Every place that showed a deadline used to build the variable key from a
 * risk-classification letter itself and to spell out A, B and C one by one.
 * That made "there is one deadline per risk classification" an assumption
 * baked into five templates. The report path has no classification, so the
 * assumption had to go: callers ask which deadlines apply and render
 * whatever comes back, however many that turns out to be.
 *
 * A deadline of zero is the seeded default and means "not configured". It
 * is left out here, which is what the classification deadlines already did
 * through their truthiness checks.
 */
final class Indieningstermijnen
{
    /** Municipality variable holding the deadline for the report path. */
    public const MELDING_KEY = 'indieningstermijn_melding';

    /**
     * Permit-path deadlines in display order: the risk-classification
     * letter mapped onto the size wording the form uses alongside it.
     *
     * @var array<string, string>
     */
    public const CLASSIFICATIES = [
        'A' => 'klein',
        'B' => 'middelgroot',
        'C' => 'groot',
    ];

    public static function classificatieKey(string $classificatie): string
    {
        return 'indieningstermijn_'.strtolower($classificatie);
    }

    /**
     * The configured deadlines that apply to the path the form is on: only
     * the report deadline once the scan concluded that a report suffices,
     * only the classification deadlines once it concluded that a permit is
     * needed, and every configured deadline while the scan has not reached
     * either conclusion yet.
     *
     * That last case is not hypothetical: the step asking for the event
     * dates comes before the scan, so the first time this list is shown
     * neither outcome is known.
     *
     * A vooraankondiging is the exception. It walks the same date step and
     * the same summary, but it is neither a report nor a permit
     * application, and it is out of scope here by decision, so it keeps
     * seeing exactly what it saw before: the classification deadlines and
     * nothing else.
     *
     * @return list<array{key: string, classificatie: string|null, omschrijving: string, weeks: int}>
     */
    public static function forState(FormState $state): array
    {
        $isVooraankondiging = $state->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging';

        $termijnen = [];

        if ($state->get('isMelding') !== true) {
            $termijnen = self::classificaties($state);
        }

        if (! $isVooraankondiging && $state->get('isVergunningaanvraag') !== true) {
            $melding = self::melding($state);
            if ($melding !== null) {
                $termijnen[] = $melding;
            }
        }

        return $termijnen;
    }

    /**
     * The configured per-classification deadlines, regardless of path. Used
     * by the risk-scan step, which exists only to determine a
     * classification and therefore always talks about these three.
     *
     * @return list<array{key: string, classificatie: string|null, omschrijving: string, weeks: int}>
     */
    public static function classificaties(FormState $state): array
    {
        $termijnen = [];

        foreach (self::CLASSIFICATIES as $classificatie => $omschrijving) {
            $key = self::classificatieKey($classificatie);
            $weeks = self::weeks($state, $key);

            if ($weeks !== null) {
                $termijnen[] = [
                    'key' => $key,
                    'classificatie' => $classificatie,
                    'omschrijving' => $omschrijving,
                    'weeks' => $weeks,
                ];
            }
        }

        return $termijnen;
    }

    /**
     * @return array{key: string, classificatie: string|null, omschrijving: string, weeks: int}|null
     */
    public static function melding(FormState $state): ?array
    {
        $weeks = self::weeks($state, self::MELDING_KEY);

        if ($weeks === null) {
            return null;
        }

        return [
            'key' => self::MELDING_KEY,
            'classificatie' => null,
            'omschrijving' => 'melding',
            'weeks' => $weeks,
        ];
    }

    /**
     * The number of weeks a municipality configured for one deadline, or
     * null when it configured none. Zero counts as none.
     */
    public static function weeks(FormState $state, string $key): ?int
    {
        $weeks = $state->get('gemeenteVariabelen.'.$key);

        if (empty($weeks)) {
            return null;
        }

        return (int) $weeks;
    }
}
