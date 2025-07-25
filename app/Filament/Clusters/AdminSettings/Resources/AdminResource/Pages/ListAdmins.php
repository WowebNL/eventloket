<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdminResource\Pages;

use App\Enums\Role;
use App\Filament\Clusters\AdminSettings\Resources\AdminResource;
use App\Mail\AdminInviteMail;
use App\Models\AdminInvite;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ListAdmins extends ListRecords
{
    protected static string $resource = AdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invite')
                ->label(__('admin/resources/admin.actions.invite.label'))
                ->icon('heroicon-o-envelope')
                ->modalSubmitActionLabel(__('admin/resources/admin.actions.invite.modal_submit_action_label'))
                ->modalWidth(MaxWidth::Medium)
                ->form([
                    TextInput::make('email')
                        ->label(__('admin/resources/admin.actions.invite.form.email.label'))
                        ->email()
                        ->required(),
                    Radio::make('role')
                        ->label(__('admin/resources/admin.actions.invite.form.role.label'))
                        ->visible(fn (): bool => auth()->user()->role == Role::Admin)
                        ->required()
                        ->options([
                            Role::MunicipalityAdmin->value => __('admin/resources/admin.actions.invite.form.role.options.municipality_admin.label'),
                            Role::Admin->value => __('admin/resources/admin.actions.invite.form.role.options.admin.label'),
                        ])
                        ->descriptions([
                            Role::MunicipalityAdmin->value => __('admin/resources/admin.actions.invite.form.role.options.municipality_admin.description'),
                            Role::Admin->value => __('admin/resources/admin.actions.invite.form.role.options.admin.description'),
                        ]),
                ])
                ->action(function ($data) {
                    /** @var \App\Models\Organisation $tenant */
                    $tenant = Filament::getTenant();

                    $adminInvite = AdminInvite::create([
                        'municipality_id' => $tenant->id,
                        'email' => $data['email'],
                        'role' => auth()->user()->role == Role::Admin ? $data['role'] : Role::MunicipalityAdmin,
                        'token' => Str::uuid(),
                    ]);

                    Mail::to($adminInvite->email)
                        ->send(new AdminInviteMail($adminInvite));

                    Notification::make()
                        ->title(__('admin/resources/admin.actions.invite.notification.title'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
