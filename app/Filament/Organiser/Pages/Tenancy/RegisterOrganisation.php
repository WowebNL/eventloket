<?php

namespace App\Filament\Organiser\Pages\Tenancy;

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;

class RegisterOrganisation extends RegisterTenant
{
    /**
     * @var view-string
     */
    protected static string $view = 'filament.organiser.pages.tenancy.register-tenant';

    public static function getLabel(): string
    {
        return __('organiser/pages/tenancy/register.label');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('organiser/pages/tenancy/register.form.name.label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('coc_number')
                    ->label(__('organiser/pages/tenancy/register.form.coc_number.label'))
                    ->unique()
                    ->validationMessages([
                        'unique' => __('organiser/pages/tenancy/register.form.coc_number.validation.unique'),
                    ])
                    ->required()
                    ->length(8),
                TextInput::make('address')
                    ->label(__('organiser/pages/tenancy/register.form.address.label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('organiser/pages/tenancy/register.form.email.label'))
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('organiser/pages/tenancy/register.form.phone.label'))
                    ->maxLength(255),
            ]);
    }

    public function noOrganisationAction(): Action
    {
        return Action::make('noOrganisation')
            ->link()
            ->label(__('organiser/pages/tenancy/register.actions.no_organisation.label'))
            ->visible(fn (): bool => auth()->user()->organisations()->doesntExist())
            ->action(function () {
                $organisation = Organisation::create([
                    'type' => OrganisationType::Personal,
                    'name' => 'Mijn omgeving',
                ]);

                $organisation->users()->attach(auth()->user(), [
                    'role' => OrganisationRole::Admin,
                ]);

                $this->redirect(Filament::getUrl($organisation));
            });
    }

    protected function handleRegistration(array $data): Organisation
    {
        $organisation = Organisation::create([
            'type' => OrganisationType::Business,
            ...$data,
        ]);

        $organisation->users()->attach(auth()->user(), [
            'role' => OrganisationRole::Admin,
        ]);

        return $organisation;
    }
}
