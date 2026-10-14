<?php

namespace App\Observers;

use App\Exceptions\MunicipalityFormQuestionLimitReached;
use App\Models\MunicipalityFormQuestion;

class MunicipalityFormQuestionObserver
{
    /**
     * Guards the per-municipality cap and assigns the next order value.
     *
     * The cap lives here rather than only in the Filament pages so a direct
     * call (an API, a console command, a crafted POST) cannot get past it.
     */
    public function creating(MunicipalityFormQuestion $question): void
    {
        $existing = MunicipalityFormQuestion::query()
            ->where('municipality_id', $question->municipality_id)
            ->count();

        $max = MunicipalityFormQuestion::maxPerMunicipality();

        if ($existing >= $max) {
            throw new MunicipalityFormQuestionLimitReached(
                sprintf('A municipality can have at most %d form questions.', $max),
            );
        }

        if ($question->order === null || $question->order < 1) {
            $highest = (int) MunicipalityFormQuestion::query()
                ->where('municipality_id', $question->municipality_id)
                ->max('order');

            $question->order = $highest + 1;
        }
    }

    /**
     * Keeps the stored shape consistent: options only make sense for the
     * types that render them, and an empty path selection is stored as null
     * ("applies to every path") so readers have one shape to check.
     */
    public function saving(MunicipalityFormQuestion $question): void
    {
        if (! $question->type->needsOptions()) {
            $question->options = null;
        } elseif (is_array($question->options)) {
            $question->options = array_values(array_filter(
                array_map(fn ($option): string => trim((string) $option), $question->options),
                fn (string $option): bool => $option !== '',
            ));
        }

        if ($question->show_for_aanvraag_types === []) {
            $question->show_for_aanvraag_types = null;
        }
    }
}
