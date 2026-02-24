<?php

namespace App\Filament\Organiser\Clusters\Settings\Pages;

use App\Filament\Organiser\Concerns\HasOrganisationAddressForm;
use App\Models\Organisation;
use App\ValueObjects\PostbusAddress;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class EditOrganisationProfile extends EditTenantProfile
{
    use HasOrganisationAddressForm;

    public function mount(): void
    {
        parent::mount();
        /** @var Organisation $tenant */
        $tenant = $this->tenant;

        if ($tenant->isPostbus()) {
            /** @var PostbusAddress $postbusAddress */
            $postbusAddress = $tenant->postbus_address;
            $this->data['use_postbus'] = true;
            $this->data['bag_address'] = [
                'huisnummer' => $postbusAddress->postbusnummer,
                'postcode' => $postbusAddress->postcode,
                'woonplaatsnaam' => $postbusAddress->woonplaatsnaam,
                'straatnaam' => 'Postbus',
            ];
        } else {
            $this->bagAddress = $tenant->bag_address?->toArray() ?? [];
        }
    }

    public static function getLabel(): string
    {
        return __('organiser/pages/tenancy/profile.label');
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->getOrganisationFormFields());
    }
}
