<?php

namespace App\Filament\Admin\Resources\UserResource\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

class Reset2faAction
{
    public static function make(): Action
    {
        return Action::make('reset_2fa')
            ->label(__('admin/resources/all-users.actions.reset_2fa.label'))
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('admin/resources/all-users.actions.reset_2fa.modal_heading'))
            ->modalDescription(__('admin/resources/all-users.actions.reset_2fa.modal_description'))
            ->modalSubmitActionLabel(__('admin/resources/all-users.actions.reset_2fa.modal_submit_action_label'))
            ->modalWidth(Width::Medium)
            ->visible(fn (User $record): bool => $record->app_authentication_secret !== null || $record->app_authentication_recovery_codes !== null)
            ->action(function (User $record) {
                $record->app_authentication_secret = null;
                $record->app_authentication_recovery_codes = null;
                $record->save();

                Notification::make()
                    ->title(__('admin/resources/all-users.actions.reset_2fa.notification.title'))
                    ->body(__('admin/resources/all-users.actions.reset_2fa.notification.body', ['name' => $record->name]))
                    ->success()
                    ->send();

                activity()
                    ->event('updated')
                    ->performedOn($record)
                    ->log('User 2FA reset by admin');
            });
    }
}
