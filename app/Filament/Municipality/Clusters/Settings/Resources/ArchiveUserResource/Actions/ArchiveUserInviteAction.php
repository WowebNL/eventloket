<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Actions;

use App\Enums\Role;
use App\Filament\Shared\Actions\InviteAction;
use App\Mail\MunicipalityInviteMail;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ArchiveUserInviteAction
{
    public static function make(): Action
    {
        return InviteAction::make()
            ->modelLabel(__('municipality/resources/archive_user.label'))
            ->schema([
                TextInput::make('name')
                    ->label(__('municipality/resources/archive_user.actions.invite.form.name.label'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('municipality/resources/archive_user.actions.invite.form.email.label'))
                    ->email()
                    ->required()
                    ->rules([
                        fn () => function (string $attribute, $value, Closure $fail) {
                            if (MunicipalityInvite::where('email', $value)->exists()) {
                                $fail(__('municipality/resources/archive_user.actions.invite.form.email.validation.already_invited'));
                            }
                        },
                    ])
                    ->maxLength(255),
                Select::make('role')
                    ->label(__('municipality/resources/archive_user.actions.invite.form.role.label'))
                    ->options([
                        Role::ArchiveCoordinator->value => Role::ArchiveCoordinator->getLabel(),
                        Role::ArchiveReviewer->value => Role::ArchiveReviewer->getLabel(),
                    ])
                    ->required(),
            ])
            ->action(function ($data) {
                /** @var Municipality $tenant */
                $tenant = Filament::getTenant();

                $municipalityInvite = MunicipalityInvite::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => Role::from($data['role']),
                    'token' => Str::uuid(),
                ]);

                $municipalityInvite->municipalities()->attach($tenant->id);

                Mail::to($municipalityInvite->email)
                    ->send(new MunicipalityInviteMail($municipalityInvite));

                Notification::make()
                    ->title(__('municipality/resources/archive_user.actions.invite.notification.title'))
                    ->success()
                    ->send();
            });
    }
}
