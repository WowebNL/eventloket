<?php

namespace App\Filament\Shared\Resources\OrganiserUsers\Actions;

use App\Enums\OrganisationRole;
use App\Filament\Shared\Actions\InviteAction;
use App\Mail\OrganisationInviteMail;
use App\Models\Organisation;
use App\Models\OrganisationInvite;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrganiserUserInviteAction
{
    public static function make(?Organisation $organisation = null): Action
    {
        return InviteAction::make()
            ->modelLabel(__('resources/organiser_user.label'))
            ->schema([
                TextInput::make('name')
                    ->label(__('organiser/resources/user.actions.invite.form.name.label'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('organiser/resources/user.actions.invite.form.email.label'))
                    ->email()
                    ->required()
                    ->unique(table: User::class)
                    ->rules([
                        function () use ($organisation) {
                            return function (string $attribute, $value, Closure $fail) use ($organisation) {
                                $org = $organisation ?? static::getOrganisation();

                                if (OrganisationInvite::where('organisation_id', $org->id)->where('email', $value)->exists()) {
                                    $fail(__('admin/resources/advisory.actions.invite.form.email.validation.already_invited'));
                                }
                            };
                        },
                    ])
                    ->maxLength(255),
                Checkbox::make('makeAdmin')
                    ->label(__('organiser/resources/user.actions.invite.form.make_admin.label'))
                    ->helperText(__('organiser/resources/user.actions.invite.form.make_admin.helper_text')),
            ])
            ->action(function ($data) use ($organisation) {
                $org = $organisation ?? static::getOrganisation();

                $organisationInvite = OrganisationInvite::create([
                    'organisation_id' => $org->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['makeAdmin'] ? OrganisationRole::Admin : OrganisationRole::Member,
                    'token' => Str::uuid(),
                ]);

                Mail::to($organisationInvite->email)
                    ->send(new OrganisationInviteMail($organisationInvite));

                Notification::make()
                    ->title(__('organiser/resources/user.actions.invite.notification.title'))
                    ->success()
                    ->send();
            });
    }

    protected static function getOrganisation(): Organisation
    {
        if (Filament::hasTenancy()) {
            /** @var Organisation $tenant */
            $tenant = Filament::getTenant();

            return $tenant;
        }

        throw new \Exception('No organisation context available');
    }
}
