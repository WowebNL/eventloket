<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\State\FormState;

/**
 * Leidt uit een FormState af welke "aard" de aanvraag heeft. De canonieke
 * bron is de content-template op stap 17 "Type aanvraag" (zie
 * `TypeAanvraagStep`), die toont wat het uiteindelijk wordt:
 *
 *   {% if waarvoorWiltUEventloketGebruiken == 'vooraankondiging' %}
 *       Vooraankondiging
 *   {% elif wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer == 'Nee' %}
 *       Melding
 *   {% else %}
 *       Evenementenvergunning
 *   {% endif %}
 *
 * We volgen die expressie 1-op-1. Let op: als het "wegen afsluiten"-veld
 * leeg is (bijv. verborgen, of meldingsroute niet afgemaakt), valt het in
 * de `else`-tak en wordt het een **vergunning** — dat is exact OF's
 * default. Vergunning is dus de veilige default; melding vereist een
 * expliciete "Nee".
 *
 * Eerdere implementatie las `isVergunningaanvraag`; die variabele is een
 * interne vlag voor veld-zichtbaarheid (triggert het tonen van
 * vergunning-specifieke velden), niet de uiteindelijke aard-keuze.
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

        // Nieuw ReportQuestion-systeem: alle actieve vragen met 'Ja' beantwoord → melding.
        if ($state->get('gemeenteVariabelen.use_new_report_questions') === true) {
            $questions = $state->get('gemeenteVariabelen.report_questions');
            if (is_array($questions) && count($questions) > 0) {
                foreach ($questions as $index => $_question) {
                    $position = (int) $index + 1;
                    if ($state->get(sprintf('reportQuestion_%d', $position)) !== 'Ja') {
                        return self::VERGUNNING;
                    }
                }

                return self::MELDING;
            }

            return self::VERGUNNING;
        }

        if ($state->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') {
            return self::MELDING;
        }

        return self::VERGUNNING;
    }
}
