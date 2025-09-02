<?php

namespace App\Filament\Public\Pages;

use App\Enums\Role;
use App\Models\AdminInvite;
use App\Models\User;
use Filament\Auth\Events\Registered;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\SimplePage;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @property mixed $form
 */
class AcceptAdminInvite extends SimplePage
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.accept-admin-invite';

    public AdminInvite $adminInvite;

    public array $data = [];

    public function mount(string $token)
    {
        $panel = Filament::getPanel('admin');
        $panel->boot();

        $this->adminInvite = AdminInvite::where('token', $token)->firstOrFail();

        $this->form->fill([
            'name' => $this->adminInvite->name,
            'email' => $this->adminInvite->email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('name')
                    ->label(__('filament-panels::auth/pages/register.form.name.label'))
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('email')
                    ->label(__('filament-panels::auth/pages/register.form.email.label'))
                    ->disabled()
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique('users'),
                TextInput::make('phone')
                    ->label(__('organiser/pages/auth/register.form.phone.label'))
                    ->maxLength(20),
                TextInput::make('password')
                    ->label(__('filament-panels::auth/pages/register.form.password.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->rule(Password::default())
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->same('passwordConfirmation')
                    ->validationAttribute(__('filament-panels::auth/pages/register.form.password.validation_attribute'))
                    ->helperText(app()->isProduction() ? __('organiser/pages/auth/register.form.password.helper_text') : null),
                TextInput::make('passwordConfirmation')
                    ->label(__('filament-panels::auth/pages/register.form.password_confirmation.label'))
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
            'email' => $this->adminInvite->email,
            'email_verified_at' => now(),
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => Role::Admin,
        ]);

        $this->adminInvite->delete();

        event(new Registered($user));

        Filament::auth()->login($user);

        session()->regenerate();

        $this->redirect(route('filament.admin.pages.dashboard'));
    }

    public function acceptInvite()
    {
        if (! auth()->check()) {
            abort(403);
        }

        if (auth()->user()->email != $this->adminInvite->email) {
            abort(403);
        }

        auth()->user()->update(['role' => Role::Admin]);

        $this->adminInvite->delete();

        $this->redirect(route('filament.admin.pages.dashboard'));
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin/pages/auth/accept-admin-invite.heading');
    }

    public function getSubheading(): Htmlable|string|null
    {
        return __('admin/pages/auth/accept-admin-invite.subheading');
    }
}
