<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\Enums\MunicipalityFormQuestionType;
use App\EventForm\State\FormState;
use App\EventForm\Support\ExtraQuestions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard\Step;

/**
 * De eigen vragen van een gemeente, in één stap vlak voor de bijlagen.
 *
 * Anders dan de andere stappen is het schema hier dynamisch: de vragen komen
 * uit `gemeenteVariabelen.extra_questions` in de FormState. Filament
 * ondersteunt dat (`Step::schema()` accepteert een Closure die lui met
 * parameter-injectie geëvalueerd wordt), maar `SubmissionReport` niet: die
 * walkt met reflectie door de rauwe `childComponents` en ziet bij een Closure
 * geen array. Vandaar de eigen tak in `SubmissionReport::extractEntries()`;
 * die twee horen bij elkaar.
 *
 * Deze stap heeft geen OpenForms-herkomst — de UUID is nieuw gegenereerd.
 */
final class AanvullendeVragenStep
{
    public const UUID = '50af8cc6-425b-46b5-9751-f5337d611b91';

    public static function make(): Step
    {
        return Step::make('Aanvullende vragen')
            ->key(self::UUID)
            ->schema(fn (?object $livewire): array => self::questionComponents(
                $livewire === null ? FormState::empty() : self::stateFrom($livewire),
            ));
    }

    /**
     * Bouw één component per vraag die op het huidige aanvraagpad geldt.
     *
     * @return list<Field>
     */
    public static function questionComponents(FormState $state): array
    {
        $components = [];

        foreach (ExtraQuestions::forState($state) as $question) {
            $component = self::componentFor($question);
            if ($component !== null) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private static function componentFor(array $question): ?Field
    {
        $key = ExtraQuestions::fieldKey($question);
        $type = MunicipalityFormQuestionType::tryFrom((string) ($question['type'] ?? ''));
        if ($type === null) {
            return null;
        }

        $options = self::optionsFor($question);
        if ($type->needsOptions() && $options === []) {
            // Een keuzevraag zonder opties is onbeantwoordbaar; laat 'm weg
            // in plaats van een leeg radioveld te tonen.
            return null;
        }

        $component = match ($type) {
            MunicipalityFormQuestionType::Text => Textarea::make($key)->rows(3),
            MunicipalityFormQuestionType::Radio => Radio::make($key)->options($options),
            MunicipalityFormQuestionType::Checkboxes => CheckboxList::make($key)->options($options),
        };

        $component = $component
            ->label((string) ($question['label'] ?? ''))
            ->required((bool) ($question['is_required'] ?? false));

        $helperText = $question['helper_text'] ?? null;
        if (is_string($helperText) && trim($helperText) !== '') {
            $component = $component->helperText($helperText);
        }

        return $component;
    }

    /**
     * De optiewaarde is gelijk aan de optietekst. Dat is bewust:
     * `SubmissionReport::extractOptions()` kan de opties van een CheckboxList
     * niet vertalen, dus met waarde == tekst valt er niets te vertalen en
     * blijft het antwoord in de PDF leesbaar.
     *
     * @param  array<string, mixed>  $question
     * @return array<string, string>
     */
    private static function optionsFor(array $question): array
    {
        $raw = $question['options'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $option) {
            if (! is_string($option) || trim($option) === '') {
                continue;
            }
            $options[$option] = $option;
        }

        return $options;
    }

    /**
     * De FormState uit het Livewire-component. Buiten de wizard (bijvoorbeeld
     * een render zonder pagina) valt 't terug op een lege state; de stap is
     * dan simpelweg leeg.
     */
    private static function stateFrom(object $livewire): FormState
    {
        if (! method_exists($livewire, 'state')) {
            return FormState::empty();
        }

        $state = $livewire->state();

        return $state instanceof FormState ? $state : FormState::empty();
    }
}
