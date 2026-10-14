<?php

namespace App\Filament\Admin\Resources\Zaaktypes\Schemas;

use App\Enums\ZaaktypeRole;
use App\Models\Zaaktype;
use App\Services\Zgw\ZgwConnectionResolver;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
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
                Select::make('role')
                    ->label(__('admin/resources/zaaktype.form.role.label'))
                    ->helperText(__('admin/resources/zaaktype.form.role.helper_text'))
                    ->options(ZaaktypeRole::class)
                    ->native(false),
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
                Checkbox::make('triggers_route_check')
                    ->label(__('admin/resources/zaaktype.form.triggers_route_check.label'))
                    ->helperText(__('admin/resources/zaaktype.form.triggers_route_check.helper_text'))
                    ->visible(fn (?Zaaktype $record): bool => self::isMainRow($record)),
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
                    ->visible(fn (?Zaaktype $record): bool => self::isMainRow($record))
                    ->columnSpanFull(),
                Placeholder::make('managed_by_municipality')
                    ->hiddenLabel()
                    ->content(__('admin/resources/zaaktype.form.triggers_route_check.managed_by_municipality'))
                    ->visible(fn (?Zaaktype $record): bool => $record !== null && ! self::isMainRow($record))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Whether the zaaktype belongs to the shared main catalogus. Own-instance
     * rows hide the route-check and hidden-results fields because a municipality
     * with its own ZGW instance manages those on its zaaktype-koppeling instead.
     */
    private static function isMainRow(?Zaaktype $record): bool
    {
        return $record !== null && $record->connection === ZgwConnectionResolver::DEFAULT_CONNECTION;
    }
}
