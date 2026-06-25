<?php

namespace App\Filament\Admin\Resources\StatusResultaatColors\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StatusResultaatColorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('status_name')
                    ->label(__('admin/resources/status_resultaat_color.form.status_name.label'))
                    ->helperText(__('admin/resources/status_resultaat_color.form.status_name.helper_text'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('resultaat')
                    ->label(__('admin/resources/status_resultaat_color.form.resultaat.label'))
                    ->helperText(__('admin/resources/status_resultaat_color.form.resultaat.helper_text'))
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? $state : null)
                    ->unique(
                        table: 'status_resultaat_colors',
                        column: 'resultaat',
                        ignorable: fn ($record) => $record,
                        modifyRuleUsing: fn ($rule, Get $get) => $rule->where('status_name', $get('status_name')),
                    )
                    ->validationMessages([
                        'unique' => __('admin/resources/status_resultaat_color.form.resultaat.unique'),
                    ]),
                ColorPicker::make('color')
                    ->label(__('admin/resources/status_resultaat_color.form.color.label'))
                    ->required(),
            ]);
    }
}
