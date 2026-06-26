<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\MunicipalityZgwConnectionResource;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\CreateMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\EditMunicipalityZgwConnection;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create();
    $this->beheerder = User::factory()->create(['role' => Role::KoppelingBeheerder]);
    $this->municipality->users()->attach($this->beheerder);

    $this->actingAs($this->beheerder);
    Filament::setTenant($this->municipality);
    Filament::bootCurrentPanel();
});

it('creates a connection scoped to the tenant municipality and restarts workers', function () {
    Artisan::spy();

    livewire(CreateMunicipalityZgwConnection::class)
        ->fillForm([
            'zaken_url' => 'https://gemeente.example.com/zaken/api/v1/',
            'client_id' => 'gemeente-client',
            'client_secret' => 'gemeente-secret-at-least-32-bytes-long',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $connection = MunicipalityZgwConnection::first();

    expect($connection->municipality_id)->toBe($this->municipality->id)
        ->and($connection->client_secret)->toBe('gemeente-secret-at-least-32-bytes-long');

    Artisan::shouldHaveReceived('call')->with('horizon:terminate');
});

it('rejects a client secret shorter than 32 bytes', function () {
    livewire(CreateMunicipalityZgwConnection::class)
        ->fillForm([
            'client_id' => 'gemeente-client',
            'client_secret' => 'too-short',
        ])
        ->call('create')
        ->assertHasFormErrors(['client_secret']);
});

it('keeps the existing secret when the field is left blank on edit', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create([
        'client_secret' => 'gemeente-secret-at-least-32-bytes-long',
    ]);

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm(['bronorganisatie_rsin' => '111111111'])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();

    expect($connection->client_secret)->toBe('gemeente-secret-at-least-32-bytes-long')
        ->and($connection->bronorganisatie_rsin)->toBe('111111111');
});

it('does not surface the stored secret in the edit form', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create([
        'client_secret' => 'gemeente-secret-at-least-32-bytes-long',
    ]);

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->assertFormFieldExists('client_secret')
        ->assertFormSet(['client_secret' => null]);
});

it('is not accessible to a reviewer', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);
    $this->actingAs($reviewer);

    expect(MunicipalityZgwConnectionResource::canAccess())->toBeFalse();
});
