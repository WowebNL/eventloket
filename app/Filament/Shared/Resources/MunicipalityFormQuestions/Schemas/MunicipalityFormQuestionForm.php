<?php

namespace App\Filament\Shared\Resources\MunicipalityFormQuestions\Schemas;

use App\Enums\MunicipalityFormQuestionType;
use App\Enums\ZaaktypeRole;
use App\Models\MunicipalityFormQuestion;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MunicipalityFormQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order')
                    ->label(__('resources/municipality_form_question.form.order.label'))
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    // Only meaningful once the record exists: on create the
                    // observer assigns the next free position.
                    ->visible(fn (?MunicipalityFormQuestion $record): bool => $record !== null),
                Select::make('type')
                    ->label(__('resources/municipality_form_question.form.type.label'))
                    ->helperText(__('resources/municipality_form_question.form.type.helper_text'))
                    ->options(MunicipalityFormQuestionType::class)
                    ->default(MunicipalityFormQuestionType::Text)
                    ->required()
                    ->live(),
                Textarea::make('label')
                    ->label(__('resources/municipality_form_question.form.label.label'))
                    ->helperText(__('resources/municipality_form_question.form.label.helper_text'))
                    ->required()
                    ->maxLength(1000)
                    ->columnSpanFull(),
                TextInput::make('helper_text')
                    ->label(__('resources/municipality_form_question.form.helper_text.label'))
                    ->helperText(__('resources/municipality_form_question.form.helper_text.helper_text'))
                    ->maxLength(500)
                    ->columnSpanFull(),
                TagsInput::make('options')
                    ->label(__('resources/municipality_form_question.form.options.label'))
                    ->helperText(__('resources/municipality_form_question.form.options.helper_text'))
                    ->visible(fn (Get $get): bool => self::typeNeedsOptions($get('type')))
                    ->required(fn (Get $get): bool => self::typeNeedsOptions($get('type')))
                    // TagsInput has no minItems(), so the minimum lives in the
                    // validation rules instead.
                    ->rules(['array', 'min:2'])
                    ->columnSpanFull(),
                CheckboxList::make('show_for_aanvraag_types')
                    ->label(__('resources/municipality_form_question.form.show_for_aanvraag_types.label'))
                    ->helperText(__('resources/municipality_form_question.form.show_for_aanvraag_types.helper_text'))
                    ->options(self::aanvraagTypeOptions())
                    ->columnSpanFull(),
                Toggle::make('is_required')
                    ->label(__('resources/municipality_form_question.form.is_required.label'))
                    ->helperText(__('resources/municipality_form_question.form.is_required.helper_text')),
                Toggle::make('is_active')
                    ->label(__('resources/municipality_form_question.form.is_active.label'))
                    ->helperText(__('resources/municipality_form_question.form.is_active.helper_text'))
                    ->default(true),
            ]);
    }

    /**
     * The three aanvraag paths a question can be limited to, keyed by the
     * backing values of the roles `DetermineAanvraagType` produces. The
     * doorkomst role is deliberately absent: it is derived from the route
     * check and never chosen on the event form.
     *
     * @return array<string, string>
     */
    public static function aanvraagTypeOptions(): array
    {
        return [
            ZaaktypeRole::Vergunning->value => __('resources/municipality_form_question.aanvraag_types.vergunning'),
            ZaaktypeRole::Melding->value => __('resources/municipality_form_question.aanvraag_types.melding'),
            ZaaktypeRole::Vooraankondiging->value => __('resources/municipality_form_question.aanvraag_types.vooraankondiging'),
        ];
    }

    /**
     * Form state holds either the enum (when hydrated from the model) or its
     * raw string value (after the select is changed), so accept both.
     */
    private static function typeNeedsOptions(mixed $type): bool
    {
        if ($type instanceof MunicipalityFormQuestionType) {
            return $type->needsOptions();
        }

        return is_string($type)
            && MunicipalityFormQuestionType::tryFrom($type)?->needsOptions() === true;
    }
}
