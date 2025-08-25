<?php

namespace App\Filament\Actions\OrganiserUser;

use App\Enums\OrganisationRole;
use App\Mail\OrganisationInviteMail;
use App\Models\Organisation;
use App\Models\OrganisationInvite;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'invite';
    }

    protected ?Closure $getOrganisationUsing = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('organiser/resources/user.actions.invite.label'));

        $this->icon('heroicon-o-envelope');

        $this->modalSubmitActionLabel(__('organiser/resources/user.actions.invite.modal_submit_action_label'));

        $this->modalWidth(Width::Medium);

        $this->schema([
            TextInput::make('name')
                ->label(__('organiser/resources/user.actions.invite.form.name.label'))
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('organiser/resources/user.actions.invite.form.email.label'))
                ->email()
                ->required()
                ->maxLength(255),
            Checkbox::make('makeAdmin')
                ->label(__('organiser/resources/user.actions.invite.form.make_admin.label'))
                ->helperText(__('organiser/resources/user.actions.invite.form.make_admin.helper_text')),
        ]);

        $this->action(function ($data) {
            $organisation = $this->getRelationship();

            $organisationInvite = OrganisationInvite::create([
                'organisation_id' => $organisation->id,
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

    public function organisation(?Closure $organisation): static
    {
        $this->getOrganisationUsing = $organisation;

        return $this;
    }

    public function getRelationship(): Organisation
    {
        if (Filament::hasTenancy()) {
            /** @var Organisation $tenant */
            $tenant = Filament::getTenant();

            return $tenant;
        }

        return $this->evaluate($this->getOrganisationUsing);
    }
}
