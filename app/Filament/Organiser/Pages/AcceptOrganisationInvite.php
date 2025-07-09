<?php

namespace App\Filament\Organiser\Pages;

use App\Models\OrganisationInvite;
use App\Models\User;
use Filament\Events\Auth\Registered;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @property mixed $form
 */
class AcceptOrganisationInvite extends SimplePage
{
    use InteractsWithForms;

    protected static string $view = 'filament.organiser.pages.accept-organisation-invite';

    public OrganisationInvite $organisationInvite;

    public array $data = [];

    public function mount(string $token)
    {
        $panel = Filament::getPanel('organiser');
        $panel->boot();

        $this->organisationInvite = OrganisationInvite::where('token', $token)->firstOrFail();

        $this->form->fill([
            'email' => $this->organisationInvite->email,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('name')
                    ->label(__('filament-panels::pages/auth/register.form.name.label'))
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('email')
                    ->label(__('filament-panels::pages/auth/register.form.email.label'))
                    ->disabled()
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique('users'),
                TextInput::make('phone')
                    ->label(__('organiser/pages/auth/register.form.phone.label'))
                    ->maxLength(255),
                TextInput::make('password')
                    ->label(__('filament-panels::pages/auth/register.form.password.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->rule(Password::default())
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->same('passwordConfirmation')
                    ->validationAttribute(__('filament-panels::pages/auth/register.form.password.validation_attribute')),
                TextInput::make('passwordConfirmation')
                    ->label(__('filament-panels::pages/auth/register.form.password_confirmation.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->dehydrated(false),
            ]);
    }

    public function create()
    {
        $data = $this->form->getState();

        $user = User::create([
            'name' => $data['name'],
            'email' => $this->organisationInvite->email,
            'email_verified_at' => now(),
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => $this->organisationInvite->role,
        ]);

        /** @phpstan-ignore-next-line */
        $this->organisationInvite->organisation->users()->attach(auth()->user(), [
            'role' => $this->organisationInvite->role,
        ]);

        $this->organisationInvite->delete();

        event(new Registered($user));

        Filament::auth()->login($user);

        session()->regenerate();

        $this->redirect(route('filament.organiser.pages.dashboard', ['tenant' => $this->organisationInvite->organisation_id]));
    }

    public function acceptInvite()
    {
        if (! auth()->check()) {
            abort(403);
        }

        if (auth()->user()->email != $this->organisationInvite->email) {
            abort(403);
        }

        /** @phpstan-ignore-next-line */
        $this->organisationInvite->organisation->users()->attach(auth()->user(), [
            'role' => $this->organisationInvite->role,
        ]);

        $this->organisationInvite->delete();

        $this->redirect(route('filament.organiser.pages.dashboard', ['tenant' => $this->organisationInvite->organisation_id]));
    }

    public function getHeading(): string|Htmlable
    {
        return __('organiser/pages/auth/accept-organisation-invite.heading');
    }

    public function getSubheading(): Htmlable|string|null
    {
        return __('organiser/pages/auth/accept-organisation-invite.subheading');
    }
}
