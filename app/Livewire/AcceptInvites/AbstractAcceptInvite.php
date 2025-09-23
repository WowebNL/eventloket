<?php

namespace App\Livewire\AcceptInvites;

use App\Enums\Role;
use App\Exceptions\InviteNotFoundException;
use App\Models\AdminInvite;
use App\Models\AdvisoryInvite;
use App\Models\MunicipalityInvite;
use App\Models\OrganisationInvite;
use App\Models\User;
use Filament\Auth\Events\Registered;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;

abstract class AbstractAcceptInvite extends SimplePage implements HasSchemas
{
    use InteractsWithSchemas;

    public array $data = [];

    #[Locked]
    public AdminInvite|AdvisoryInvite|MunicipalityInvite|OrganisationInvite $invite;

    abstract protected function getInviteModel(): AdminInvite|AdvisoryInvite|MunicipalityInvite|OrganisationInvite;

    abstract protected function getRole(): Role;

    abstract protected function getPanelName(): string;

    abstract protected function getTenantId(): mixed;

    abstract protected function attachTenantRelation(User $user): void;

    public function mount(string $token)
    {
        Filament::getPanel($this->getPanelName())->boot();

        if (! $invite = $this->getInviteModel()::where('token', $token)->first()) {
            throw new InviteNotFoundException;
        }

        $this->invite = $invite;

        /** @phpstan-ignore-next-line */
        $this->form->fill([
            'name' => $this->invite->name,
            'email' => $this->invite->email,
        ]);

    }

    public function create(): void
    {
        /** @phpstan-ignore-next-line */
        $data = $this->form->getState();

        $user = User::create([
            'name' => $data['name'],
            'email' => $this->invite->email,
            'email_verified_at' => now(),
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => $this->getRole(),
        ]);

        $this->attachTenantRelation($user);

        $this->invite->delete();

        event(new Registered($user));

        Filament::auth()->login($user);

        session()->regenerate();

        $routeParams = $this->getTenantId() ? ['tenant' => $this->getTenantId()] : [];
        $this->redirect(route('filament.'.$this->getPanelName().'.pages.dashboard', $routeParams));
    }

    public function acceptInvite(): void
    {
        if (! auth()->check()) {
            abort(403);
        }

        if (auth()->user()->email != $this->invite->email) {
            abort(403);
        }

        $this->attachTenantRelation(auth()->user());

        if ($this->getPanelName() == 'admin') {
            auth()->user()->update(['role' => $this->getRole()]);
        }

        $this->invite->delete();

        $routeParams = $this->getTenantId() ? ['tenant' => $this->getTenantId()] : [];
        $this->redirect(route('filament.'.$this->getPanelName().'.pages.dashboard', $routeParams));
    }

    #[Layout('filament-panels::components.layout.simple')]
    public function render(): View
    {
        return view('livewire.accept-invite');
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
                    ->required()
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
}
