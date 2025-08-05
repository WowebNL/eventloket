<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\Role;
use App\Filament\Resources\UserResource;
use App\Mail\AdminInviteMail;
use App\Models\AdminInvite;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invite')
                ->label(__('admin/resources/user.actions.invite.label'))
                ->icon('heroicon-o-envelope')
                ->modalSubmitActionLabel(__('admin/resources/user.actions.invite.modal_submit_action_label'))
                ->modalWidth(MaxWidth::Medium)
                ->visible(fn (): bool => in_array(auth()->user()->role, [Role::Admin, Role::MunicipalityAdmin]))
                ->form([
                    TextInput::make('name')
                        ->label(__('admin/resources/user.actions.invite.form.name.label'))
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('admin/resources/user.actions.invite.form.email.label'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function ($data) {
                    /** @var \App\Models\Organisation $tenant */
                    $tenant = Filament::getTenant();

                    $adminInvite = AdminInvite::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'role' => Role::Reviewer,
                        'token' => Str::uuid(),
                    ]);

                    $adminInvite->municipalities()->attach($tenant->id);

                    Mail::to($adminInvite->email)
                        ->send(new AdminInviteMail($adminInvite));

                    Notification::make()
                        ->title(__('admin/resources/user.actions.invite.notification.title'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
