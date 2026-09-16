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

    // "name" is not a critical field, so saving it does not trigger the
    // deactivation confirmation modal.
    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm(['name' => 'Nieuwe naam'])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();

    expect($connection->client_secret)->toBe('gemeente-secret-at-least-32-bytes-long')
        ->and($connection->name)->toBe('Nieuwe naam');
});

it('holds the save behind a confirmation when a critical field changes', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->active()->create([
        'zaken_url' => 'https://old.example.com/zaken/api/v1/',
    ]);

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm(['zaken_url' => 'https://new.example.com/zaken/api/v1/'])
        ->call('save')
        ->assertActionMounted('confirmConnectionCriticalChange');

    // Nothing is persisted and the connection stays live until the user confirms.
    $connection->refresh();

    expect($connection->zaken_url)->toBe('https://old.example.com/zaken/api/v1/')
        ->and($connection->isActive())->toBeTrue();
});

it('saves and deactivates the connection once the critical change is confirmed', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->active()->create([
        'zaken_url' => 'https://old.example.com/zaken/api/v1/',
    ]);

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm(['zaken_url' => 'https://new.example.com/zaken/api/v1/'])
        ->call('save')
        ->assertActionMounted('confirmConnectionCriticalChange')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $connection->refresh();

    expect($connection->zaken_url)->toBe('https://new.example.com/zaken/api/v1/')
        ->and($connection->isActive())->toBeFalse()
        ->and($connection->last_verified_at)->toBeNull();
});

it('saves a non-critical change without asking for confirmation', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->active()->create();

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm(['name' => 'Nieuwe naam'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionNotMounted('confirmConnectionCriticalChange');

    $connection->refresh();

    expect($connection->name)->toBe('Nieuwe naam')
        ->and($connection->isActive())->toBeTrue();
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
            'vertrouwelijkheid_map.visibility.organiser' => 'vertrouwelijk',
            'vertrouwelijkheid_map.upload_default.organiser' => 'vertrouwelijk',
            'vertrouwelijkheid_map.upload_default.system' => 'confidentieel',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();

    // toEqual (==), not toBe (===): JSON object key order is not significant and
    // differs by database driver (MySQL reorders the keys of the stored map).
    expect($connection->vertrouwelijkheid_map)->toEqual([
        'visibility' => ['organiser' => 'vertrouwelijk'],
        'upload_default' => ['organiser' => 'vertrouwelijk', 'system' => 'confidentieel'],
    ]);
});

it('fans the gemeente group choice out to every municipal handler role', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create();

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm([
            'vertrouwelijkheid_map.visibility.reviewer' => 'confidentieel',
            'vertrouwelijkheid_map.upload_default.reviewer' => 'confidentieel',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();

    expect($connection->vertrouwelijkheid_map['visibility'])->toBe([
        'reviewer' => 'confidentieel',
        'coordinator' => 'confidentieel',
        'municipality_admin' => 'confidentieel',
        'reviewer_municipality_admin' => 'confidentieel',
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
            'vertrouwelijkheid_map.visibility.organiser' => 'zaakvertrouwelijk',
            'vertrouwelijkheid_map.visibility.advisor' => null,
            'vertrouwelijkheid_map.upload_default.reviewer' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();

    expect($connection->vertrouwelijkheid_map)->toBe([
        'visibility' => ['organiser' => 'zaakvertrouwelijk'],
    ]);
});

it('blocks a maximum that runs down with a validation error', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create();

    // The organiser is given a maximum (intern) above the advisor's: a broader
    // audience must always see at least what a narrower one sees.
    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm([
            'vertrouwelijkheid_map.visibility.organiser' => 'intern',
            'vertrouwelijkheid_map.visibility.advisor' => 'openbaar',
            'vertrouwelijkheid_map.visibility.reviewer' => 'intern',
        ])
        ->call('save')
        ->assertHasFormErrors(['vertrouwelijkheid_map.visibility.organiser']);

    // Nothing is persisted while the map is invalid.
    expect($connection->fresh()->vertrouwelijkheid_map)->toBeNull();
});

it('blocks a gemeente maximum below the advisor maximum', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create();

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm([
            'vertrouwelijkheid_map.visibility.organiser' => 'openbaar',
            'vertrouwelijkheid_map.visibility.advisor' => 'intern',
            'vertrouwelijkheid_map.visibility.reviewer' => 'beperkt_openbaar',
        ])
        ->call('save')
        ->assertHasFormErrors(['vertrouwelijkheid_map.visibility.advisor']);

    expect($connection->fresh()->vertrouwelijkheid_map)->toBeNull();
});

it('accepts maxima that run up', function () {
    $connection = MunicipalityZgwConnection::factory()->for($this->municipality)->create();

    livewire(EditMunicipalityZgwConnection::class, ['record' => $connection->getKey()])
        ->fillForm([
            'vertrouwelijkheid_map.visibility.organiser' => 'openbaar',
            'vertrouwelijkheid_map.visibility.advisor' => 'beperkt_openbaar',
            'vertrouwelijkheid_map.visibility.reviewer' => 'intern',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($connection->fresh()->vertrouwelijkheid_map['visibility'])->toMatchArray([
        'organiser' => 'openbaar',
        'advisor' => 'beperkt_openbaar',
        'reviewer' => 'intern',
    ]);
});

it('clamps a map written outside the form as a safety net', function () {
    // pruneVertrouwelijkheidMap runs on save (after the form validation above has
    // already blocked a maximum that runs down), and is the second line for
    // seeders and direct writes: a broader group is clamped up to the narrower
    // one instead of being stored below it.
    $result = MunicipalityZgwConnectionResource::pruneVertrouwelijkheidMap([
        'vertrouwelijkheid_map' => [
            'visibility' => [
                'organiser' => 'intern',
                'advisor' => 'openbaar',
                'reviewer' => 'openbaar',
            ],
        ],
    ]);

    expect($result['vertrouwelijkheid_map']['visibility']['organiser'])->toBe('intern')
        ->and($result['vertrouwelijkheid_map']['visibility']['advisor'])->toBe('intern')
        ->and($result['vertrouwelijkheid_map']['visibility']['reviewer'])->toBe('intern')
        // The gemeente choice is still fanned out to the other municipal roles.
        ->and($result['vertrouwelijkheid_map']['visibility']['coordinator'])->toBe('intern');
});

it('converts a legacy map of level sets to a maximum on save', function () {
    // Compatibility with maps stored before the maximum was introduced: each set
    // is read as its most confidential member.
    $result = MunicipalityZgwConnectionResource::pruneVertrouwelijkheidMap([
        'vertrouwelijkheid_map' => [
            'visibility' => [
                'organiser' => ['openbaar'],
                'advisor' => ['openbaar', 'beperkt_openbaar'],
                'reviewer' => ['openbaar', 'beperkt_openbaar', 'intern'],
            ],
        ],
    ]);

    expect($result['vertrouwelijkheid_map']['visibility'])->toBe([
        'organiser' => 'openbaar',
        'advisor' => 'beperkt_openbaar',
        'reviewer' => 'intern',
        'coordinator' => 'intern',
        'municipality_admin' => 'intern',
        'reviewer_municipality_admin' => 'intern',
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
