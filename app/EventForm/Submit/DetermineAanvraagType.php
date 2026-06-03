<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\State\FormState;

/**
 * Leidt uit een FormState af welke "aard" de aanvraag heeft. Twee
 * paden — afhankelijk van of de gemeente al naar het nieuwe
 * ReportQuestion-systeem is overgeschakeld of niet:
 *
 * 1. Legacy (`use_new_report_questions !== true`):
 *    - `waarvoorWiltUEventloketGebruiken == 'vooraankondiging'` → Vooraankondiging
 *    - `wordenErGebiedsontsluitings…VoorHetVerkeer == 'Nee'`     → Melding
 *    - anders                                                    → Evenementenvergunning
 *
 * 2. Nieuw ReportQuestion-systeem (`use_new_report_questions === true`):
 *    - Vooraankondiging-keuze blijft hetzelfde voorop in de keten.
 *    - Eén `reportQuestion_N` op 'Nee' (bij een actieve vraag) → Vergunning
 *      (één Nee = niet-meldbaar; dezelfde semantiek als
 *      `FormDerivedState::isVergunningaanvraag()` in 't nieuwe pad).
 *    - Alle actieve `reportQuestion_N` op 'Ja' → Melding.
 *    - Geen / niet-volledige antwoorden → Vergunning (veilige default).
 *
 * Vergunning is in beide paden de fallback wanneer het scan-resultaat
 * niet expliciet een meldings- of vooraankondigings-pad oplevert.
 */
final class DetermineAanvraagType
{
    public const VOORAANKONDIGING = 'vooraankondiging';

    public const VERGUNNING = 'vergunning';

    public const MELDING = 'melding';

    public function forState(FormState $state): string
    {
        if ($state->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging') {
            return self::VOORAANKONDIGING;
        }

        if ($state->get('gemeenteVariabelen.use_new_report_questions') === true) {
            return $this->fromReportQuestions($state);
        }

        if ($state->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') {
            return self::MELDING;
        }

        return self::VERGUNNING;
    }

    /**
     * Verwerk het nieuwe ReportQuestion-pad. We lopen alle actieve
     * vragen langs (gemeente kan minder dan 10 vragen hebben):
     *   - één 'Nee' → vergunning (cascade gestopt op een knock-out)
     *   - alle 'Ja' → melding
     *   - mix met onbeantwoorde slots → vergunning (default — scan
     *     niet doorlopen)
     */
    private function fromReportQuestions(FormState $state): string
    {
        $questions = $state->get('gemeenteVariabelen.report_questions');
        if (! is_array($questions) || $questions === []) {
            return self::VERGUNNING;
        }

        $alleJa = true;
        foreach ($questions as $index => $_question) {
            $position = (int) $index + 1;
            $antwoord = $state->get(sprintf('reportQuestion_%d', $position));

            if ($antwoord === 'Nee') {
                return self::VERGUNNING;
            }
            if ($antwoord !== 'Ja') {
                $alleJa = false;
            }
        }

        return $alleJa ? self::MELDING : self::VERGUNNING;
    }
}
