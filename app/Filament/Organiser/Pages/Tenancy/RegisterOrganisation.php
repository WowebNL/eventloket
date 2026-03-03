<?php

namespace App\Filament\Organiser\Pages\Tenancy;

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Filament\Organiser\Concerns\HasOrganisationAddressForm;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\ValueObjects\PostbusAddress;
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
        if ($data['use_postbus'] === true) {
            $postbusAddress = PostbusAddress::fromArray([
                'postbusnummer' => $data['bag_address']['huisnummer'],
                'postcode' => $data['bag_address']['postcode'],
                'woonplaatsnaam' => $data['bag_address']['woonplaatsnaam'],
            ]);

            $data['postbus_address'] = $postbusAddress;
            $data['bag_id'] = null;
            $data['address'] = $postbusAddress->weergavenaam();
        } else {
            $data['postbus_address'] = null;

            if ($this->bagAddress) {
                $data['bag_id'] = Arr::get($this->bagAddress, 'id');
            }
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
