<?php

namespace App\Filament\Admin\Resources\Zaaktypes\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ZaaktypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/zaaktype.form.name.label'))
                    ->disabled()
                    ->required(),
                TextInput::make('zgw_zaaktype_url')
                    ->label(__('admin/resources/zaaktype.form.zgw_zaaktype_url.label'))
                    ->disabled()
                    ->url()
                    ->required(),
                Select::make('municipality_id')
                    ->label(__('admin/resources/zaaktype.form.municipality_id.label'))
                    ->disabled()
                    ->relationship('municipality', 'name'),
                Checkbox::make('is_active')
                    ->label(__('admin/resources/zaaktype.form.is_active.label'))
                    ->disabled()
                    ->required(),
                CheckboxList::make('hidden_resultaat_types')
                    ->label(__('admin/resources/zaaktype.form.hidden_resultaat_types.label'))
                    ->helperText(__('admin/resources/zaaktype.form.hidden_resultaat_types.helper_text'))
                    ->options(function ($record) {
                        if (! $record) {
                            return [];
                        }

                        return collect($record->getResultaatTypen())
                            ->mapWithKeys(fn (array $type) => [
                                $type['url'] => $type['omschrijving'],
                            ]);
                    })
                    ->visible(fn ($record) => $record !== null)
                    ->columnSpanFull(),
            ]);
    }
}
