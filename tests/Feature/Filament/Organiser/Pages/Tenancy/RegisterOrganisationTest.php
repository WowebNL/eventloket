<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Filament\Organiser\Pages\Tenancy\RegisterOrganisation;
use App\Models\Organisation;
use App\Models\User;
use App\Services\LocatieserverService;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $user = User::factory()->create([
        'role' => Role::Organiser,
    ]);

    actingAs($user);
});

test('organiser can register organisation with postbus address', function () {
    livewire(RegisterOrganisation::class)
        ->fillForm([
            'name' => 'Test Organisatie Postbus',
            'coc_number' => '87654321',
            'use_postbus' => true,
            'bag_address' => [
                'postcode' => '5678CD',
                'huisnummer' => '123',
                'straatnaam' => 'Postbus',
                'woonplaatsnaam' => 'Rotterdam',
            ],
            'email' => 'postbus@domain.com',
            'phone' => '0698765432',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $organisation = Organisation::where('name', 'Test Organisatie Postbus')->first();
    expect($organisation)
        ->not->toBeNull()
        ->and($organisation->type)->toBe(OrganisationType::Business)
        ->and($organisation->coc_number)->toBe('87654321')
        ->and($organisation->bag_id)->toBeNull()
        ->and($organisation->address)->toBe('Postbus 123, 5678CD Rotterdam');

    expect(auth()->user()->organisations()->wherePivot('role', OrganisationRole::Admin->value)->exists())->toBeTrue();
});

test('postbus checkbox sets straatnaam to Postbus', function () {
    $livewire = livewire(RegisterOrganisation::class)
        ->fillForm([
            'name' => 'Test Organisatie',
            'coc_number' => '11111111',
            'use_postbus' => false,
            'bag_address' => [
                'postcode' => '1234AB',
                'huisnummer' => '1',
                'straatnaam' => 'Teststraat',
                'woonplaatsnaam' => 'Amsterdam',
            ],
        ]);

    $livewire->set('data.use_postbus', true);

    expect($livewire->get('data.bag_address.straatnaam'))->toBe('Postbus');
});

test('unchecking postbus checkbox clears straatnaam', function () {
    $livewire = livewire(RegisterOrganisation::class)
        ->fillForm([
            'name' => 'Test Organisatie',
            'coc_number' => '22222222',
            'use_postbus' => true,
            'bag_address' => [
                'postcode' => '1234AB',
                'huisnummer' => '123',
                'straatnaam' => 'Postbus',
                'woonplaatsnaam' => 'Amsterdam',
            ],
        ]);

    $livewire->set('data.use_postbus', false);

    expect($livewire->get('data.bag_address.straatnaam'))->toBeNull();
});

test('bag validation is disabled when postbus is selected', function () {
    $this->mock(LocatieserverService::class)
        ->shouldNotReceive('getBagObjectByPostcodeHuisnummer');

    $livewire = livewire(RegisterOrganisation::class)
        ->fillForm([
            'name' => 'Test Organisatie',
            'coc_number' => '33333333',
            'use_postbus' => true,
            'bag_address' => [
                'postcode' => '1234AB',
                'huisnummer' => '123',
                'straatnaam' => 'Postbus',
                'woonplaatsnaam' => 'Amsterdam',
            ],
        ]);

    $livewire->set('data.bag_address.postcode', '5678CD');
    $livewire->set('data.bag_address.huisnummer', '456');

    $livewire->call('register')->assertHasNoFormErrors();
});

test('postbus address clears huisletter and huisnummertoevoeging', function () {
    $livewire = livewire(RegisterOrganisation::class)
        ->fillForm([
            'name' => 'Test Organisatie',
            'coc_number' => '66666666',
            'use_postbus' => false,
            'bag_address' => [
                'postcode' => '1234AB',
                'huisnummer' => '1',
                'huisletter' => 'A',
                'huisnummertoevoeging' => 'II',
                'straatnaam' => 'Teststraat',
                'woonplaatsnaam' => 'Amsterdam',
            ],
        ]);

    $livewire->set('data.use_postbus', true);

    expect($livewire->get('data.bag_address.huisletter'))->toBeNull();
    expect($livewire->get('data.bag_address.huisnummertoevoeging'))->toBeNull();
    expect($livewire->get('data.bag_address.straatnaam'))->toBe('Postbus');
});
