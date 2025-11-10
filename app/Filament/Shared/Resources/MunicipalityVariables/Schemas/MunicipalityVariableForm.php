<?php

namespace App\Filament\Shared\Resources\MunicipalityVariables\Schemas;

use App\Enums\MunicipalityVariableType;
use App\Filament\Admin\Resources\MunicipalityVariables\Pages\CreateMunicipalityVariable;
use App\Models\Municipality;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon as ComponentsIcon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MunicipalityVariableForm
{
    public static function configure(Schema $schema): Schema
    {
        $municipality = self::getMunicipality($schema->getLivewire());

        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('resources/municipality_variable.form.name.label'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, Get $get, string $operation, ?string $state) => $operation != 'edit' && $get('type') != MunicipalityVariableType::ReportQuestion ? $set('key', Str::slug($state, '_')) : null),

                TextInput::make('key')
                    ->label(__('resources/municipality_variable.form.key.label'))
                    ->required()
                    ->maxLength(255)
                    ->disabled(function (Get $get, $operation) {
                        return $operation === 'edit' || $get('type') === MunicipalityVariableType::ReportQuestion;
                    })
                    ->dehydrated()
                    ->belowContent([
                        ComponentsIcon::make(Heroicon::InformationCircle),
                        __('resources/municipality_variable.form.key.info'),
                    ])
                    ->rules('regex:/^[A-Za-z0-9_]+$/')
                    ->unique(
                        table: 'municipality_variables',
                        column: 'key',
                        ignorable: fn ($record) => $record,
                        modifyRuleUsing: function ($rule, $livewire) use ($municipality) {
                            return $rule->where('municipality_id', $municipality->id ?? null)->whereNull('deleted_at');
                        },
                    ),

                Select::make('type')
                    ->label(__('resources/municipality_variable.form.type.label'))
                    ->required()
                    ->options(MunicipalityVariableType::class)
                    ->default(MunicipalityVariableType::Text)
                    ->selectablePlaceholder(false)
                    ->live()
                    // reset the value when switching type to avoid invalid stale state
                    ->afterStateUpdated(function (Set $set, $state, $livewire) use ($municipality) {
                        if (in_array($state, [MunicipalityVariableType::DateRange, MunicipalityVariableType::TimeRange, MunicipalityVariableType::DateTimeRange])) {
                            $set('value', ['start' => null, 'end' => null]);
                        } else {
                            $set('value', null);
                        }

                        if ($state === MunicipalityVariableType::ReportQuestion && ! $livewire instanceof CreateMunicipalityVariable) {
                            // if type is report question, auto generate unique report_question key which ends with a number between 1-5
                            // works when previous question is deleted as well
                            $numbers = $municipality->reportQuestions->map(fn ($item) => (int) substr($item->key, -1))->toArray();
                            $options = array_diff(range(1, 5), $numbers);
                            $key = Arr::first($options);
                            $set('key', 'report_question_'.$key);
                            $set('order', $key);
                        }
                    })
                    ->in(function ($livewire) use ($municipality) {
                        // disable creating of report question type as default municipality variable by admin
                        // or when municipality already has 5 report Question variables
                        if ($livewire instanceof CreateMunicipalityVariable || ($municipality instanceof Municipality && $municipality->reportQuestions->count() >= 5)) {
                            return [
                                MunicipalityVariableType::Text,
                                MunicipalityVariableType::Number,
                                MunicipalityVariableType::Boolean,
                                MunicipalityVariableType::DateRange,
                                MunicipalityVariableType::TimeRange,
                                MunicipalityVariableType::DateTimeRange,
                            ];
                        }

                        return MunicipalityVariableType::cases();

                    })
                    ->disabledOn('edit')
                    ->disableOptionWhen(function ($value, $livewire) use ($municipality) {
                        // disable creating of report question type as default municipality variable by admin
                        // or when municipality already has 5 report Question variables
                        if ($livewire instanceof CreateMunicipalityVariable || ($municipality instanceof Municipality && $municipality->reportQuestions->count() >= 5)) {
                            return $value === MunicipalityVariableType::ReportQuestion->value;
                        }

                        return false;
                    })
                    ->belowContent(function ($value, $livewire, $operation) use ($municipality) {
                        if ($municipality instanceof Municipality && $operation == 'create' && $municipality->reportQuestions->count() >= 5) {
                            return __('Voor de gemeente :name is het maximale aantal van 5 meldingsvragen bereikt, hierdoor is het niet mogelijk om nieuwe meldingsvragen aan te maken.', ['name' => $municipality->name]);
                        }

                    }),

                // Render exactly ONE 'value' field, based on 'type'
                Group::make(function (Get $get) {
                    return match ($get('type')) {
                        MunicipalityVariableType::DateRange => [
                            Grid::make()
                                ->schema([
                                    DatePicker::make('value.start')
                                        ->label(__('resources/municipality_variable.form.start.label'))
                                        ->seconds(false)
                                        ->closeOnDateSelection()
                                        ->required(),

                                    DatePicker::make('value.end')
                                        ->label(__('resources/municipality_variable.form.end.label'))
                                        ->seconds(false)
                                        ->closeOnDateSelection()
                                        ->after('value.start')
                                        ->required(),
                                ]),
                        ],
                        MunicipalityVariableType::TimeRange => [
                            Grid::make()
                                ->schema([
                                    TimePicker::make('value.start')
                                        ->label(__('resources/municipality_variable.form.start.label'))
                                        ->seconds(false)
                                        ->closeOnDateSelection()
                                        ->required(),

                                    TimePicker::make('value.end')
                                        ->label(__('resources/municipality_variable.form.end.label'))
                                        ->seconds(false)
                                        ->closeOnDateSelection()
                                        ->after('value.start')
                                        ->required(),
                                ]),
                        ],
                        MunicipalityVariableType::DateTimeRange => [
                            Grid::make()
                                ->schema([
                                    DateTimePicker::make('value.start')
                                        ->label(__('resources/municipality_variable.form.start.label'))
                                        ->seconds(false)
                                        ->closeOnDateSelection()
                                        ->required(),

                                    DateTimePicker::make('value.end')
                                        ->label(__('resources/municipality_variable.form.end.label'))
                                        ->seconds(false)
                                        ->closeOnDateSelection()
                                        ->after('value.start')
                                        ->required(),
                                ]),
                        ],
                        MunicipalityVariableType::Boolean => [
                            Toggle::make('value')
                                ->label(__('resources/municipality_variable.form.value.label'))
                                ->required(),
                        ],
                        MunicipalityVariableType::Number => [
                            TextInput::make('value')
                                ->label(__('resources/municipality_variable.form.value.label'))
                                ->numeric()
                                ->required()
                                ->maxLength(255),
                        ],
                        default => [
                            TextInput::make('value')
                                ->label(__('resources/municipality_variable.form.value.label'))
                                ->required()
                                ->maxLength(255),
                        ],
                    };
                }),

                Select::make('order')
                    ->required()
                    ->label(__('resources/municipality_variable.form.order.label'))
                    ->options(function () use ($municipality) {
                        $count = $municipality->reportQuestions->count();
                        $range = range(1, $count);

                        return array_combine($range, $range);
                    })
                    ->visible(fn (Get $get, $operation) => $get('type') === MunicipalityVariableType::ReportQuestion && $operation == 'edit'),

                Section::make(__('Meldingsvraag informatie'))
                    ->afterHeader([
                        ComponentsIcon::make(Heroicon::InformationCircle),
                    ])
                    ->visible(fn (Get $get) => $get('type') === MunicipalityVariableType::ReportQuestion)
                    ->schema([
                        TextEntry::make('report_question_info')
                            ->hiddenLabel()
                            ->state(__('Een meldingsvraag wordt getoond in het aanvraagformulier om te bepalen of voor de aanvraag een vergunning aangevraagd moet worden of dat een melding voldoende is. Er bestaat al een basisset van standaard meldingsvragen, de vragen die hier worden aangemaakt gelden alleen voor de gemeente :name en worden toegevoegd aan de basisset. De vraag moet te beantwoorden zijn met "ja" of "nee". Indien het antwoord "ja" is zal dan zal er een vervolgvraag worden gesteld om te bepalen of de aanvraag een melding is. Indien het antwoord op de vraag "nee" is dan zal de aanvraag worden gekenmerkt als vergunningsplichtig. Vul bij de waarde de vraag in welke te beantwoorden is met "ja" of "nee".', ['name' => $municipality->name ?? 'onbekend']))
                            ->visible(fn (Get $get) => $get('type') === MunicipalityVariableType::ReportQuestion)
                            ->size(TextSize::Medium),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function getMunicipality($livewire): ?Municipality
    {
        if ($livewire instanceof RelationManager) {
            /** @var Municipality $record */
            $record = $livewire->getOwnerRecord();

            return $record;
        } elseif (Filament::getCurrentPanel()->getId() === 'municipality') {
            /** @var Municipality $record */
            $record = Filament::getTenant();

            return $record;
        }

        return null;
    }
}
