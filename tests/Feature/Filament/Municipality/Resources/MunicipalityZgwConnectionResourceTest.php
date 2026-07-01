<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\MunicipalityZgwConnectionResource;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\CreateMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\EditMunicipalityZgwConnection;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages\ListMunicipalityZgwConnections;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
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

it('stores the vertrouwelijkheid map from the form', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create();

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm([
            'vertrouwelijkheid_map.visibility.organiser' => ['zaakvertrouwelijk', 'vertrouwelijk'],
            'vertrouwelijkheid_map.upload_default.organiser' => 'vertrouwelijk',
            'vertrouwelijkheid_map.upload_default.system' => 'confidentieel',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();

    // toEqual (==), not toBe (===): JSON object key order is not significant and
    // differs by database driver (MySQL reorders the keys of the stored map).
    expect($connection->vertrouwelijkheid_map)->toEqual([
        'visibility' => ['organiser' => ['zaakvertrouwelijk', 'vertrouwelijk']],
        'upload_default' => ['organiser' => 'vertrouwelijk', 'system' => 'confidentieel'],
    ]);
});

it('fans the gemeente group choice out to every municipal handler role', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create();

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm([
            'vertrouwelijkheid_map.visibility.reviewer' => ['zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'],
            'vertrouwelijkheid_map.upload_default.reviewer' => 'confidentieel',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();

    $allLevels = ['zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'];

    expect($connection->vertrouwelijkheid_map['visibility'])->toBe([
        'reviewer' => $allLevels,
        'coordinator' => $allLevels,
        'municipality_admin' => $allLevels,
        'reviewer_municipality_admin' => $allLevels,
    ])->and($connection->vertrouwelijkheid_map['upload_default'])->toBe([
        'reviewer' => 'confidentieel',
        'coordinator' => 'confidentieel',
        'municipality_admin' => 'confidentieel',
        'reviewer_municipality_admin' => 'confidentieel',
    ]);
});

it('prunes empty roles so they fall back to the defaults', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create();

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm([
            'vertrouwelijkheid_map.visibility.organiser' => ['zaakvertrouwelijk'],
            'vertrouwelijkheid_map.visibility.advisor' => [],
            'vertrouwelijkheid_map.upload_default.reviewer' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();

    expect($connection->vertrouwelijkheid_map)->toBe([
        'visibility' => ['organiser' => ['zaakvertrouwelijk']],
    ]);
});

it('hides the per-role vertrouwelijkheid fields when no upload tab is enabled', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create([
        'show_bestanden_tab' => false,
        'show_adviesvragen_tab' => false,
        'show_organisatievragen_tab' => false,
    ]);

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->assertFormFieldIsHidden('vertrouwelijkheid_map.visibility.organiser')
        ->assertFormFieldIsHidden('vertrouwelijkheid_map.visibility.reviewer')
        ->assertFormFieldExists('vertrouwelijkheid_map.upload_default.system');
});

it('shows the per-role vertrouwelijkheid fields when at least one upload tab is enabled', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create([
        'show_bestanden_tab' => false,
        'show_adviesvragen_tab' => true,
        'show_organisatievragen_tab' => false,
    ]);

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->assertFormFieldIsVisible('vertrouwelijkheid_map.visibility.organiser')
        ->assertFormFieldIsVisible('vertrouwelijkheid_map.visibility.reviewer');
});

it('exposes the verify connection row action', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create();

    livewire(ListMunicipalityZgwConnections::class)
        ->assertActionExists(TestAction::make('verify')->table($connection));
});

it('disables the activate action until the connection is verified', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create([
        'last_verified_at' => null,
    ]);

    livewire(ListMunicipalityZgwConnections::class)
        ->assertActionDisabled(TestAction::make('activate')->table($connection));
});

it('activates a verified connection', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create([
        'last_verified_at' => now(),
    ]);

    livewire(ListMunicipalityZgwConnections::class)
        ->assertActionEnabled(TestAction::make('activate')->table($connection))
        ->callAction(TestAction::make('activate')->table($connection));

    expect($connection->fresh()->isActive())->toBeTrue();
});

it('deactivates an active connection', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->active()->create();

    livewire(ListMunicipalityZgwConnections::class)
        ->callAction(TestAction::make('deactivate')->table($connection));

    expect($connection->fresh()->isActive())->toBeFalse();
});

it('is not accessible to a reviewer', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);
    $this->actingAs($reviewer);

    expect(MunicipalityZgwConnectionResource::canAccess())->toBeFalse();
});
