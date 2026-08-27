<?php

declare(strict_types=1);

namespace App\EventForm\Services;

use App\Enums\MunicipalityVariableType;
use App\Models\Municipality;

/**
 * Levert de gemeente-variabelen die in het formulier gebruikt worden
 * (thresholds, gemeente-specifieke labels). Oorspronkelijk OF's
 * `fetch-from-service` naar /api/municipality-variables/{brk_id}.
 *
 * Wanneer een gemeente migrated is naar het ReportQuestion-systeem
 * (`use_new_report_questions === true`) filteren we de
 * `report_question`-typed variabelen weg — die worden dan via de
 * aparte `ReportQuestion`-tabel geserveerd.
 */
class MunicipalityVariablesService
{
    /**
     * @return list<array{id: int, name: string, key: string, type: string, value: mixed, is_default: bool}>
     */
    public function forMunicipality(Municipality $municipality): array
    {
        $variables = $municipality
            ->variables()
            ->withTrashed()
            ->get();

        if ($municipality->use_new_report_questions) {
            $variables = $variables->reject(
                fn ($variable): bool => $variable->type === MunicipalityVariableType::ReportQuestion,
            );
        }

        return $variables
            ->map(fn ($variable): array => [
                'id' => $variable->id,
                'name' => $variable->name,
                'key' => $variable->key,
                'type' => $variable->type,
                'value' => $variable->formatted_value,
                'is_default' => $variable->is_default,
            ])
            ->values()
            ->all();
    }

    /**
     * Dezelfde set, maar geplat als key → value map — handig voor direct
     * gebruik als `gemeenteVariabelen` variable in de FormState.
     *
     * Wanneer `use_new_report_questions === true` voegen we ook de
     * actieve ReportQuestion-records toe onder `report_questions`
     * (gesorteerd op `order`), plus de toggle zelf zodat het formulier
     * kan beslissen welke variant van de meldingvragen-cascade 'ie
     * rendert.
     *
     * @return array<string, mixed>
     */
    public function forMunicipalityAsKeyValue(Municipality $municipality): array
    {
        $map = [];
        foreach ($this->forMunicipality($municipality) as $entry) {
            // TimeRange/DateRange/DateTimeRange worden door de Filament-
            // admin-form als `{start, end}`-object opgeslagen — direct
            // bruikbaar voor labels die `gemeenteVariabelen.muziektijden.start`
            // verwachten. Geen shape-conversie nodig.
            $map[$entry['key']] = $entry['value'];
        }

        $map['use_new_report_questions'] = (bool) $municipality->use_new_report_questions;
        if ($municipality->use_new_report_questions) {
            $map['report_questions'] = $municipality
                ->reportQuestions()
                ->where('is_active', true)
                ->orderBy('order')
                ->get()
                ->map(fn ($q): array => [
                    'id' => (int) $q->id,
                    'order' => (int) $q->order,
                    'question' => (string) $q->question,
                ])
                ->all();
        } else {
            $map['report_questions'] = [];
        }

        $map['extra_questions'] = $this->extraQuestionsFor($municipality);

        return $map;
    }

    /**
     * De per-gemeente ingestelde "Aanvullende vragen" (`MunicipalityFormQuestion`),
     * alleen de actieve, gesorteerd op `order`.
     *
     * Deze lijst gaat integraal mee in `gemeenteVariabelen` en daarmee in de
     * `form_state_snapshot` van de zaak. Daardoor is 'ie bij het indienen
     * bevroren: wijzigt of verwijdert de gemeente later een vraag, dan blijft
     * de PDF van een ingediende aanvraag kloppen. Haal deze lijst dus niet
     * alsnog live uit de database bij het genereren van de PDF — dat breekt
     * stilletjes alle historische inzendingen.
     *
     * @return list<array{id: int, order: int, type: string, label: string, helper_text: string|null, options: list<string>, is_required: bool, show_for_aanvraag_types: list<string>}>
     */
    private function extraQuestionsFor(Municipality $municipality): array
    {
        return $municipality
            ->formQuestions()
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(fn ($question): array => [
                'id' => (int) $question->id,
                'order' => (int) $question->order,
                'type' => $question->type->value,
                'label' => (string) $question->label,
                'helper_text' => $question->helper_text === null ? null : (string) $question->helper_text,
                'options' => array_values(array_map(
                    fn ($option): string => (string) $option,
                    is_array($question->options) ? $question->options : [],
                )),
                'is_required' => (bool) $question->is_required,
                'show_for_aanvraag_types' => array_values(array_map(
                    fn ($type): string => (string) $type,
                    is_array($question->show_for_aanvraag_types) ? $question->show_for_aanvraag_types : [],
                )),
            ])
            ->all();
    }
}
