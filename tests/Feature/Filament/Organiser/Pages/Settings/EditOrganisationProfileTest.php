<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Filament\Organiser\Clusters\Settings\Pages\EditOrganisationProfile;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $this->user = User::factory()->create([
        'role' => Role::Organiser,
    ]);

    actingAs($this->user);
});

test('edit profile mounts postbus address from postbus_address column', function () {
    $organisation = Organisation::factory()->postbus('456', '1234AB', 'Amsterdam')->create([
        'type' => OrganisationType::Business,
        'name' => 'Postbus Org',
        'coc_number' => '11111111',
    ]);
    $organisation->users()->attach($this->user, ['role' => OrganisationRole::Admin->value]);

    Filament::setTenant($organisation);

    $component = livewire(EditOrganisationProfile::class);

    expect($component->get('data.use_postbus'))->toBeTrue()
        ->and($component->get('data.bag_address.huisnummer'))->toBe('456')
        ->and($component->get('data.bag_address.postcode'))->toBe('1234AB')
        ->and($component->get('data.bag_address.woonplaatsnaam'))->toBe('Amsterdam')
        ->and($component->get('data.bag_address.straatnaam'))->toBe('Postbus');
});

test('saving postbus address persists postbus_address column and clears bag_id', function () {
    $organisation = Organisation::factory()->create([
        'type' => OrganisationType::Business,
        'name' => 'Normal Org',
        'coc_number' => '22222222',
        'bag_id' => 'adr-some-id',
        'address' => 'Teststraat 1, 1234AB Amsterdam',
    ]);
    $organisation->users()->attach($this->user, ['role' => OrganisationRole::Admin->value]);

    Filament::setTenant($organisation);

    livewire(EditOrganisationProfile::class)
        ->fillForm([
            'use_postbus' => true,
            'bag_address' => [
                'huisnummer' => '789',
                'postcode' => '9876ZX',
                'woonplaatsnaam' => 'Utrecht',
                'straatnaam' => 'Postbus',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $organisation->refresh();

    expect($organisation->postbus_address)->not->toBeNull()
        ->and($organisation->postbus_address->postbusnummer)->toBe('789')
        ->and($organisation->postbus_address->postcode)->toBe('9876ZX')
        ->and($organisation->postbus_address->woonplaatsnaam)->toBe('Utrecht')
        ->and($organisation->bag_id)->toBeNull()
        ->and($organisation->address)->toBe('Postbus 789, 9876ZX Utrecht');
});

test('saving normal bag address clears postbus_address column', function () {
    $organisation = Organisation::factory()->postbus()->create([
        'type' => OrganisationType::Business,
        'name' => 'Postbus Org To Update',
        'coc_number' => '33333333',
    ]);
    $organisation->users()->attach($this->user, ['role' => OrganisationRole::Admin->value]);

    Filament::setTenant($organisation);

    livewire(EditOrganisationProfile::class)
        ->fillForm([
            'use_postbus' => false,
            'bag_address' => [
                'huisnummer' => '10',
                'postcode' => '1111AB',
                'woonplaatsnaam' => 'Den Haag',
                'straatnaam' => 'Kerkstraat',
            ],
            'address' => 'Kerkstraat 10, 1111AB Den Haag',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $organisation->refresh();

    expect($organisation->postbus_address)->toBeNull();
});
