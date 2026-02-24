<?php

namespace App\Filament\Organiser\Clusters\Settings\Pages;

use App\Filament\Organiser\Concerns\HasOrganisationAddressForm;
use App\Models\Organisation;
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
        $this->bagAddress = $tenant->bag_address?->toArray() ?? [];

        // Check if the organisation uses a postbus address
        if (str_contains($tenant->address, 'Postbus')) {
            $this->data['use_postbus'] = true;

            // Parse the postbus address format: "Postbus 1234, 5555AA Eindhoven"
            if (preg_match('/Postbus\s+(\d+),\s+([A-Z0-9]+)\s+(.+)$/i', $tenant->address, $matches)) {
                $this->data['bag_address'] = [
                    'huisnummer' => $matches[1],
                    'postcode' => $matches[2],
                    'woonplaatsnaam' => $matches[3],
                ];
            }

        }
    }

    public static function getLabel(): string
    {
        return __('organiser/pages/tenancy/profile.label');
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->getOrganisationFormFields());
    }
}
