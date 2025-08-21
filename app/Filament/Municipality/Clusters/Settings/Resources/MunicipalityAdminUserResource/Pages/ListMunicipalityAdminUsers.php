<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages;

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource;
use App\Mail\MunicipalityInviteMail;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ListMunicipalityAdminUsers extends ListRecords
{
    protected static string $resource = MunicipalityAdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('admin/resources/admin.actions.invite.label'))
                ->icon('heroicon-o-envelope')
                ->modalSubmitActionLabel(__('admin/resources/admin.actions.invite.modal_submit_action_label'))
                ->modalWidth(Width::Medium)
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin/resources/admin.actions.invite.form.name.label'))
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('admin/resources/admin.actions.invite.form.email.label'))
                        ->email()
                        ->required()
                        ->unique(table: User::class)
                        ->maxLength(255),
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
                        ])
                        ->default(Role::MunicipalityAdmin->value)
                        ->live(),
                    Select::make('municipalities')
                        ->multiple()
                        ->options(function () {
                            if (auth()->user()->role === Role::Admin) {
                                return Municipality::pluck('name', 'id');
                            }

                            // If the user is a MunicipalityAdmin, return only the municipalities they are associated with
                            return auth()->user()->municipalities->pluck('name', 'id');
                        })
                        ->label(__('admin/resources/admin.actions.invite.form.municipalities.label'))
                        ->visible(fn (Get $get): bool => $get('role') === Role::MunicipalityAdmin->value)
                        ->preload()
                        ->required(),
                ])
                ->action(function ($data) {

                    $municipalityInvite = MunicipalityInvite::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'role' => auth()->user()->role == Role::Admin ? $data['role'] : Role::MunicipalityAdmin,
                        'token' => Str::uuid(),
                    ]);

                    if (isset($data['municipalities']) && $data['municipalities']) {
                        $municipalityInvite->municipalities()->attach($data['municipalities']);
                    }

                    Mail::to($municipalityInvite->email)
                        ->send(new MunicipalityInviteMail($municipalityInvite));

                    Notification::make()
                        ->title(__('admin/resources/admin.actions.invite.notification.title'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
