<?php

namespace App\Filament\Shared\Resources\MunicipalityAdminUsers\Actions;

use App\Enums\Role;
use App\Filament\Shared\Actions\InviteAction;
use App\Mail\MunicipalityInviteMail;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use App\Models\User;
use App\Models\Users\AdminUser;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MunicipalityAdminUserInviteAction
{
    public static function make(): Action
    {
        return InviteAction::make()
            ->modelLabel(__('resources/municipality_admin_user.label'))
            ->schema([
                TextInput::make('name')
                    ->label(__('municipality/resources/municipality_admin.actions.invite.form.name.label'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('municipality/resources/municipality_admin.actions.invite.form.email.label'))
                    ->email()
                    ->required()
                    ->unique(table: User::class)
                    ->rules([
                        fn () => function (string $attribute, $value, Closure $fail) {
                            if (MunicipalityInvite::where('email', $value)->exists()) {
                                $fail(__('municipality/resources/municipality_admin.actions.invite.form.email.validation.already_invited'));
                            }
                        },
                    ])
                    ->maxLength(255),
                Select::make('municipalities')
                    ->multiple()
                    ->options(function () {
                        /** @var \App\Models\Users\AdminUser|\App\Models\Users\MunicipalityAdminUser|\App\Models\Users\ReviewerMunicipalityAdminUser $user */
                        $user = auth()->user();

                        if ($user instanceof AdminUser) {
                            return Municipality::pluck('name', 'id');
                        }

                        return $user->municipalities->pluck('name', 'id');
                    })
                    ->label(__('municipality/resources/municipality_admin.actions.invite.form.municipalities.label'))
                    ->preload()
                    ->required(),
                Checkbox::make('can_review')
                    ->label(__('municipality/resources/municipality_admin.actions.invite.form.can_review.label'))
                    ->helperText(__('municipality/resources/municipality_admin.actions.invite.form.can_review.helper_text')),
            ])
            ->action(function ($data) {

                $municipalityInvite = MunicipalityInvite::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['can_review'] ? Role::ReviewerMunicipalityAdmin : Role::MunicipalityAdmin,
                    'token' => Str::uuid(),
                ]);

                if (isset($data['municipalities']) && $data['municipalities']) {
                    $municipalityInvite->municipalities()->attach($data['municipalities']);
                }

                Mail::to($municipalityInvite->email)
                    ->send(new MunicipalityInviteMail($municipalityInvite));

                Notification::make()
                    ->title(__('municipality/resources/municipality_admin.actions.invite.notification.title'))
                    ->success()
                    ->send();
            });
    }
}
