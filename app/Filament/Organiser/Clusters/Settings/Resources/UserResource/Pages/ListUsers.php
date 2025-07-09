<?php

namespace App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages;

use App\Enums\OrganisationRole;
use App\Filament\Organiser\Clusters\Settings\Resources\UserResource;
use App\Mail\OrganisationInviteMail;
use App\Models\OrganisationInvite;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
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
                ->label(__('organiser/resources/user.actions.invite.label'))
                ->icon('heroicon-o-envelope')
                ->modalSubmitActionLabel(__('organiser/resources/user.actions.invite.modal_submit_action_label'))
                ->modalWidth(MaxWidth::Medium)
                ->form([
                    TextInput::make('email')
                        ->label(__('organiser/resources/user.actions.invite.form.email.label'))
                        ->email()
                        ->required(),
                    Checkbox::make('makeAdmin')
                        ->label(__('organiser/resources/user.actions.invite.form.make_admin.label'))
                        ->helperText(__('organiser/resources/user.actions.invite.form.make_admin.helper_text')),
                ])
                ->action(function ($data) {
                    /** @var \App\Models\Organisation $tenant */
                    $tenant = Filament::getTenant();

                    $organisationInvite = OrganisationInvite::create([
                        'organisation_id' => $tenant->id,
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
                }),
        ];
    }
}
