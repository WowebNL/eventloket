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
 * aparte `ReportQuestion`-tabel + ReportQuestionController geserveerd.
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

        return $map;
    }
}
