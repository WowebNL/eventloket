<?php

namespace App\Filament\Organiser\Pages\Tenancy;

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Filament\Organiser\Concerns\HasOrganisationAddressForm;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class RegisterOrganisation extends RegisterTenant
{
    use HasOrganisationAddressForm;

    /**
     * @var view-string
     */
    protected string $view = 'filament.organiser.pages.tenancy.register-tenant';

    public static function getLabel(): string
    {
        return __('organiser/pages/tenancy/register.label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->getOrganisationFormFields());
    }

    public function noOrganisationAction(): Action
    {
        return Action::make('noOrganisation')
            ->link()
            ->label(__('organiser/pages/tenancy/register.actions.no_organisation.label'))
            ->visible(function () {
                $user = auth()->user();

                if ($user instanceof OrganiserUser) {
                    return $user->organisations()->doesntExist();
                }

                return false;
            })
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
        if ($this->bagAddress) {
            $data['bag_id'] = Arr::get($this->bagAddress, 'id');
        }

        if ($data['use_postbus'] === true) {
            $postcode = $data['bag_address']['postcode'];
            $huisnummer = $data['bag_address']['huisnummer'];
            $straatnaam = 'Postbus';
            $woonplaatsnaam = $data['bag_address']['woonplaatsnaam'];

            $data['address'] = "$straatnaam $huisnummer, $postcode $woonplaatsnaam";
        }

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
