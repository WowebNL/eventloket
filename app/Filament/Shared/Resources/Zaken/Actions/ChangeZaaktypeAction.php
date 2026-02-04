<?php

namespace App\Filament\Shared\Resources\Zaken\Actions;

use App\Enums\Role;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Woweb\Openzaak\Openzaak;

class ChangeZaaktypeAction
{
    public static function make(Zaak $zaak): Action
    {
        return Action::make('change_zaaktype')
            ->label(__('resources/zaak.actions.change_zaaktype.label'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('resources/zaak.actions.change_zaaktype.modal_heading'))
            ->modalDescription(__('resources/zaak.actions.change_zaaktype.modal_description'))
            ->modalSubmitActionLabel(__('resources/zaak.actions.change_zaaktype.modal_submit_label'))
            ->visible(fn (): bool => auth()->user()->role === Role::Admin)
            ->schema([
                Select::make('new_zaaktype_id')
                    ->label(__('resources/zaak.actions.change_zaaktype.form.new_zaaktype_id.label'))
                    ->options(function () use ($zaak) {
                        return Zaaktype::where('municipality_id', $zaak->zaaktype->municipality_id)
                            ->where('is_active', true)
                            ->where('id', '!=', $zaak->zaaktype_id)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->required()
                    ->searchable()
                    ->helperText(__('resources/zaak.actions.change_zaaktype.form.new_zaaktype_id.helper_text')),
            ])
            ->action(function (array $data, Action $action) use ($zaak): void {
                $newZaaktype = Zaaktype::findOrFail($data['new_zaaktype_id']);

                try {
                    // Update zaaktype in OpenZaak
                    $openzaak = new Openzaak;
                    $openzaak->zaken()->zaken()->patch($zaak->openzaak->uuid, [
                        'zaaktype' => $newZaaktype->zgw_zaaktype_url,
                    ]);

                    // Update zaaktype in eventloket database
                    $zaak->zaaktype_id = $newZaaktype->id;
                    $zaak->save();

                    // Clear ZGW cache
                    $zaak->clearZgwCache();

                    Notification::make()
                        ->success()
                        ->title(__('resources/zaak.actions.change_zaaktype.notifications.success.title'))
                        ->body(__('resources/zaak.actions.change_zaaktype.notifications.success.body', ['zaaktype' => $newZaaktype->name]))
                        ->send();

                    // Refresh the page
                    $action->getLivewire()->dispatch('refreshZaak');
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title(__('resources/zaak.actions.change_zaaktype.notifications.error.title'))
                        ->body(__('resources/zaak.actions.change_zaaktype.notifications.error.body', ['error' => $e->getMessage()]))
                        ->send();
                }
            });
    }
}
