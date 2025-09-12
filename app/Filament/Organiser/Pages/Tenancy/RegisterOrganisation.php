<?php

namespace App\Filament\Organiser\Pages\Tenancy;

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\Services\LocatieserverService;
use App\ValueObjects\Pdok\BagObject;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Livewire\Attributes\Locked;

class RegisterOrganisation extends RegisterTenant
{
    #[Locked]
    public array $bagAddress = [];

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
        return $schema
            ->components([
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

        $organisation = Organisation::create([
            'type' => OrganisationType::Business,
            ...$data,
        ]);

        $organisation->users()->attach(auth()->user(), [
            'role' => OrganisationRole::Admin,
        ]);

        return $organisation;
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
