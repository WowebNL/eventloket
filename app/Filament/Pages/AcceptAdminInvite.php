<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\AdminInvite;
use App\Models\Municipality;
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
class AcceptAdminInvite extends SimplePage
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.accept-admin-invite';

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
            'email' => $this->adminInvite->email,
            'email_verified_at' => now(),
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => $this->adminInvite->role,
        ]);

        $tenantId = Municipality::first()->id;
        if ($this->adminInvite->role !== Role::Admin) {
            foreach ($this->adminInvite->municipalities as $key => $municipality) {
                if ($key == 0) {
                    /** @var \App\Models\Municipality $municipality */
                    $tenantId = $municipality->id;
                }
                /** @phpstan-ignore-next-line */
                $municipality->users()->attach($user);
            }
        }

        $this->adminInvite->delete();

        event(new Registered($user));

        Filament::auth()->login($user);

        session()->regenerate();

        $this->redirect(route('filament.admin.pages.dashboard', ['tenant' => $tenantId]));
    }

    public function acceptInvite()
    {
        if (! auth()->check()) {
            abort(403);
        }

        if (auth()->user()->email != $this->adminInvite->email) {
            abort(403);
        }

        $tenantId = Municipality::first()->id;
        if ($this->adminInvite->role !== Role::Admin) {
            foreach ($this->adminInvite->municipalities as $key => $municipality) {
                if ($key == 0) {
                    /** @var \App\Models\Municipality $municipality */
                    $tenantId = $municipality->id;
                }
                /** @phpstan-ignore-next-line */
                $municipality->users()->attach(auth()->user());
            }
        }

        $this->adminInvite->delete();

        $this->redirect(route('filament.admin.pages.dashboard', ['tenant' => $tenantId]));
    }

    public function getHeading(): string|Htmlable
    {
        /** @var Role $role */
        $role = $this->adminInvite->role;

        return __('admin/pages/auth/accept-admin-invite.heading', ['role' => strtolower($role->getLabel())]);
    }

    public function getSubheading(): Htmlable|string|null
    {
        return __('admin/pages/auth/accept-admin-invite.subheading');
    }
}
