<?php

namespace App\Filament\Shared\Resources\MunicipalityVariables\Schemas;

use App\Enums\MunicipalityVariableType;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MunicipalityVariableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('resources/municipality_variable.form.name.label'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, string $operation, ?string $state) => $operation != 'edit' ? $set('key', Str::slug($state, '_')) : null),

                TextInput::make('key')
                    ->label(__('resources/municipality_variable.form.key.label'))
                    ->required()
                    ->maxLength(255)
                    ->disabledOn('edit')
                    ->rules(['alpha_dash', Rule::doesntContain(['-'])])
                    ->unique(
                        table: 'municipality_variables',
                        column: 'key',
                        ignorable: fn ($record) => $record,
                        modifyRuleUsing: function ($rule) {
                            /** @phpstan-ignore-next-line */
                            return $rule->where('municipality_id', Filament::getCurrentPanel()->getId() === 'municipality' ? Filament::getTenant()->id : null);
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
                    ->afterStateUpdated(fn (Set $set, $state) => in_array($state, [MunicipalityVariableType::DateRange, MunicipalityVariableType::TimeRange, MunicipalityVariableType::DateTimeRange]) ? $set('value', ['start' => null, 'end' => null]) : $set('value', null))
                    ->disabledOn('edit'),

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
            ]);
    }
}
