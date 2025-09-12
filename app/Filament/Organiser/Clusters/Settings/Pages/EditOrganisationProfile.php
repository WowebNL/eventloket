<?php

namespace App\Filament\Organiser\Clusters\Settings\Pages;

use App\Models\Organisation;
use App\Services\LocatieserverService;
use App\ValueObjects\Pdok\BagObject;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Livewire\Attributes\Locked;

class EditOrganisationProfile extends EditTenantProfile
{
    #[Locked]
    public array $bagAddress = [];

    public function mount(): void
    {
        parent::mount();
        /** @var Organisation $tenant */
        $tenant = $this->tenant;
        $this->bagAddress = $tenant->bag_address?->toArray() ?? [];
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

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('organiser/pages/tenancy/register.form.name.label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('coc_number')
                    ->label(__('organiser/pages/tenancy/register.form.coc_number.label'))
                    ->disabled()
                    ->unique()
                    ->validationMessages([
                        'unique' => __('organiser/pages/tenancy/register.form.coc_number.validation.unique'),
                    ])
                    ->required()
                    ->length(8),
                Fieldset::make('bag_address')
                    ->label(__('organiser/pages/tenancy/register.form.address.label'))
                    ->schema([
                        TextInput::make('bag_address.postcode')
                            ->label(__('organiser/pages/tenancy/register.form.postcode.label'))
                            ->required()
                            ->maxLength(6)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state) {
                                if ($state && $this->data['bag_address']['huisnummer']) {
                                    $this->handleBagAddressChange((new LocatieserverService)->getBagObjectByPostcodeHuisnummer($state, $this->data['bag_address']['huisnummer']));
                                }
                            }),
                        TextInput::make('bag_address.huisnummer')
                            ->label(__('organiser/pages/tenancy/register.form.huisnummer.label'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state) {
                                if ($state && $this->data['bag_address']['postcode']) {
                                    $this->handleBagAddressChange((new LocatieserverService)->getBagObjectByPostcodeHuisnummer($this->data['bag_address']['postcode'], $state));
                                }
                            }),
                        TextInput::make('bag_address.huisletter')
                            ->label(__('organiser/pages/tenancy/register.form.huisletter.label'))
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state) {
                                if ($this->data['bag_address']['huisnummer'] && $this->data['bag_address']['postcode']) {
                                    $this->handleBagAddressChange((new LocatieserverService)->getBagObjectByPostcodeHuisnummer($this->data['bag_address']['postcode'], $this->data['bag_address']['huisnummer'], $state, $this->data['bag_address']['huisnummertoevoeging'] ?? null));
                                }
                            }),
                        TextInput::make('bag_address.huisnummertoevoeging')
                            ->label(__('organiser/pages/tenancy/register.form.huisnummertoevoeging.label'))
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state) {
                                if ($this->data['bag_address']['huisnummer'] && $this->data['bag_address']['postcode']) {
                                    $this->handleBagAddressChange((new LocatieserverService)->getBagObjectByPostcodeHuisnummer($this->data['bag_address']['postcode'], $this->data['bag_address']['huisnummer'], $this->data['bag_address']['huisletter'] ?? null, $state));
                                }
                            }),
                        TextInput::make('bag_address.straatnaam')
                            ->label(__('organiser/pages/tenancy/register.form.straatnaam.label'))
                            ->maxLength(255),
                        TextInput::make('bag_address.woonplaatsnaam')
                            ->label(__('organiser/pages/tenancy/register.form.woonplaatsnaam.label'))
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label(__('organiser/pages/tenancy/register.form.address.label'))
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label(__('organiser/pages/tenancy/register.form.email.label'))
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('organiser/pages/tenancy/register.form.phone.label'))
                    ->maxLength(20),
            ]);
    }

    private function handleBagAddressChange(?BagObject $bagObject): void
    {
        if ($bagObject) {
            $this->bagAddress = $bagObject->toArray();
            $this->data['bag_address'] = array_merge($this->data['bag_address'], $bagObject->toArray());
            $this->data['address'] = $bagObject->weergavenaam;
        } else {
            $this->bagAddress = [];
            $this->data['address'] = '';
        }
    }
}
