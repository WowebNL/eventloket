<?php

declare(strict_types=1);

namespace App\EventForm\State;

/**
 * Pure-functions-class voor afgeleide system-bag-keys. Pendant van
 * FormDerivedState, maar dan voor `system.*`-paden — gebruikt door
 * FormState wanneer een caller `state->get('system.X')` doet en de
 * `X` in COMPUTED_KEYS staat.
 *
 * Voor nu één key: `registration_backend` — de backend-naam waar de
 * uiteindelijke zaak heen geroute't wordt. OF leverde dit via 45
 * losstaande rules (15 gemeenten × 3 aanvraag-types). Wij hebben de
 * mapping als data hieronder en kiezen pure-functioneel.
 *
 * Aanvraag-type (vergunning/melding/vooraankondiging) wordt afgeleid
 * uit dezelfde state-velden die de Type-aanvraag-stap consulteert:
 *   - vooraankondiging als waarvoorWiltUEventloketGebruiken === 'vooraankondiging'
 *   - melding als wegen-afsluiten === 'Nee'
 *   - anders vergunning
 */
final class FormSystemDerivedState
{
    /** @var array<string, true> */
    public const COMPUTED_KEYS = [
        'registration_backend' => true,
    ];

    /**
     * Backend-mapping per gemeente per aanvraag-type. Pivot uit OF
     * (22 april 2026) — wijzig hier wanneer OF de routing aanpast.
     *
     * @var array<string, array{vergunning: string, melding: string, vooraankondiging: string}>
     */
    private const BACKEND_PER_GEMEENTE = [
        'GM0882' => ['vergunning' => 'backend23', 'vooraankondiging' => 'backend22', 'melding' => 'backend24'],
        'GM0888' => ['vergunning' => 'backend3', 'vooraankondiging' => 'backend9', 'melding' => 'backend8'],
        'GM0899' => ['vergunning' => 'backend15', 'vooraankondiging' => 'backend14', 'melding' => 'backend13'],
        'GM0917' => ['vergunning' => 'backend1', 'vooraankondiging' => 'backend4', 'melding' => 'backend6'],
        'GM0928' => ['vergunning' => 'backend21', 'vooraankondiging' => 'backend20', 'melding' => 'backend19'],
        'GM0938' => ['vergunning' => 'backend26', 'vooraankondiging' => 'backend25', 'melding' => 'backend27'],
        'GM0965' => ['vergunning' => 'backend29', 'vooraankondiging' => 'backend28', 'melding' => 'backend30'],
        'GM0971' => ['vergunning' => 'backend35', 'vooraankondiging' => 'backend34', 'melding' => 'backend36'],
        'GM0981' => ['vergunning' => 'backend38', 'vooraankondiging' => 'backend37', 'melding' => 'backend39'],
        'GM0986' => ['vergunning' => 'backend44', 'vooraankondiging' => 'backend43', 'melding' => 'backend45'],
        'GM0994' => ['vergunning' => 'backend41', 'vooraankondiging' => 'backend40', 'melding' => 'backend42'],
        'GM1729' => ['vergunning' => 'backend2', 'vooraankondiging' => 'backend5', 'melding' => 'backend7'],
        'GM1883' => ['vergunning' => 'backend32', 'vooraankondiging' => 'backend31', 'melding' => 'backend33'],
        'GM1903' => ['vergunning' => 'backend18', 'vooraankondiging' => 'backend17', 'melding' => 'backend16'],
        'GM1954' => ['vergunning' => 'backend10', 'vooraankondiging' => 'backend12', 'melding' => 'backend11'],
    ];

    public function __construct(private readonly FormState $state) {}

    /**
     * Naam van de OF-registratiebackend waar deze zaak heen geroute't
     * wordt. Vervangt 45 OF-rules (één per gemeente×type-combinatie).
     *
     * @return string|null null wanneer (a) gemeente onbekend, of
     *                     (b) onvoldoende state om type te bepalen.
     */
    public function registrationBackend(): ?string
    {
        $brkId = $this->state->get('evenementInGemeente.brk_identification');
        if (! is_string($brkId) || ! isset(self::BACKEND_PER_GEMEENTE[$brkId])) {
            return null;
        }

        $type = $this->aanvraagType();
        if ($type === null) {
            return null;
        }

        return self::BACKEND_PER_GEMEENTE[$brkId][$type];
    }

    /**
     * Bepaalt het aanvraag-type uit de huidige state. Volgt de
     * precedence-regels die OF ook gebruikte (die zijn impliciet in de
     * 45 oude rules zichtbaar):
     *   - `vooraankondiging` wint als de gebruiker dat expliciet
     *     gekozen heeft, ongeacht andere antwoorden.
     *   - `vergunning` wanneer `isVergunningaanvraag` truthy is. Die
     *     variabele is zelf afgeleid uit de scan-vragen
     *     (FormDerivedState) plus wegen-afsluiten.
     *   - `melding` wanneer wegen-afsluiten 'Nee' is en geen
     *     vergunningsplichtige scan-vraag matched.
     *   - Anders null (state nog niet rijp om te bepalen).
     *
     * @return 'vergunning'|'melding'|'vooraankondiging'|null
     */
    private function aanvraagType(): ?string
    {
        if ($this->state->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging') {
            return 'vooraankondiging';
        }
        if ($this->state->get('isVergunningaanvraag') === true) {
            return 'vergunning';
        }
        // Nieuw ReportQuestion-systeem: alle actieve vragen met 'Ja' beantwoord → melding.
        if ($this->state->get('gemeenteVariabelen.use_new_report_questions') === true) {
            $questions = $this->state->get('gemeenteVariabelen.report_questions');
            if (is_array($questions) && count($questions) > 0) {
                foreach ($questions as $index => $_question) {
                    $position = (int) $index + 1;
                    if ($this->state->get(sprintf('reportQuestion_%d', $position)) !== 'Ja') {
                        return null; // niet alle vragen beantwoord
                    }
                }

                return 'melding';
            }

            return null;
        }
        if ($this->state->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee') {
            return 'melding';
        }

        return null;
    }

    public function get(string $key): mixed
    {
        return match ($key) {
            'registration_backend' => $this->registrationBackend(),
            default => null,
        };
    }
}
