<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\MunicipalityResource;
use App\Filament\Admin\Resources\MunicipalityResource\Pages\CreateMunicipality;
use App\Filament\Admin\Resources\MunicipalityResource\Pages\EditMunicipality;
use App\Filament\Admin\Resources\MunicipalityResource\Pages\ListMunicipalities;
use App\Filament\Admin\Resources\MunicipalityResource\RelationManagers\LocationsRelationManager;
use App\Filament\Admin\Resources\MunicipalityResource\RelationManagers\MunicipalityAdminUsersRelationManager;
use App\Filament\Admin\Resources\MunicipalityResource\RelationManagers\ReviewerMunicipalityAdminUsersRelationManager;
use App\Filament\Admin\Resources\MunicipalityResource\RelationManagers\ReviewerUsersRelationManager;
use App\Filament\Admin\Resources\MunicipalityResource\RelationManagers\VariablesRelationManager;
use App\Models\Municipality;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

covers(
    MunicipalityResource::class,
    ListMunicipalities::class,
    CreateMunicipality::class,
    EditMunicipality::class,
    LocationsRelationManager::class,
    MunicipalityAdminUsersRelationManager::class,
    ReviewerMunicipalityAdminUsersRelationManager::class,
    ReviewerUsersRelationManager::class,
    VariablesRelationManager::class,
);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($this->admin);
});

test('admin can view municipality list', function () {
    $municipalities = Municipality::factory()->count(3)->create();

    livewire(ListMunicipalities::class)
        ->assertOk()
        ->assertCanSeeTableRecords($municipalities);
});

test('admin can create municipality', function () {
    livewire(CreateMunicipality::class)
        ->fillForm([
            'name' => 'Test Municipality',
            'brk_identification' => 'GM1234',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Municipality::where('name', 'Test Municipality')->exists())->toBeTrue();
});

test('municipality form validates name is required', function () {
    livewire(CreateMunicipality::class)
        ->fillForm([
            'name' => '',
            'brk_identification' => 'GM1234',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

test('municipality form validates brk_identification is required', function () {
    livewire(CreateMunicipality::class)
        ->fillForm([
            'name' => 'Test Municipality',
            'brk_identification' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['brk_identification' => 'required']);
});

test('municipality form validates brk_identification starts with GM', function () {
    livewire(CreateMunicipality::class)
        ->fillForm([
            'name' => 'Test Municipality',
            'brk_identification' => 'AB1234',
        ])
        ->call('create')
        ->assertHasFormErrors(['brk_identification']);
});

test('admin can edit municipality', function () {
    $municipality = Municipality::factory()->create([
        'name' => 'Original Name',
    ]);

    livewire(EditMunicipality::class, [
        'record' => $municipality->id,
    ])
        ->fillForm([
            'name' => 'Updated Name',
            'brk_identification' => $municipality->brk_identification,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($municipality->fresh()->name)->toBe('Updated Name');
});

test('admin can delete municipality from edit page', function () {
    $municipality = Municipality::factory()->create();

    livewire(EditMunicipality::class, [
        'record' => $municipality->id,
    ])
        ->callAction(DeleteAction::class);

    expect(Municipality::find($municipality->id))->toBeNull();
});

test('municipality table has name column that is searchable', function () {
    Municipality::factory()->create(['name' => 'Alpha City']);
    Municipality::factory()->create(['name' => 'Beta City']);

    $alpha = Municipality::where('name', 'Alpha City')->first();
    $beta = Municipality::where('name', 'Beta City')->first();

    livewire(ListMunicipalities::class)
        ->searchTable('Alpha')
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);
});

test('municipality table has brk_identification column', function () {
    livewire(ListMunicipalities::class)
        ->assertTableColumnExists('brk_identification');
});

test('admin can access locations relation manager', function () {
    $municipality = Municipality::factory()->create();

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk();
});

test('admin can access municipality admin users relation manager', function () {
    $municipality = Municipality::factory()->create();

    livewire(MunicipalityAdminUsersRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk();
});

test('admin can access reviewer municipality admin users relation manager', function () {
    $municipality = Municipality::factory()->create();

    livewire(ReviewerMunicipalityAdminUsersRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk();
});

test('admin can access reviewer users relation manager', function () {
    $municipality = Municipality::factory()->create();

    livewire(ReviewerUsersRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk();
});

test('admin can access variables relation manager', function () {
    $municipality = Municipality::factory()->create();

    livewire(VariablesRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk();
});

test('municipality resource has correct model', function () {
    expect(MunicipalityResource::getModel())->toBe(Municipality::class);
});

test('municipality resource has correct navigation sort', function () {
    expect(MunicipalityResource::getNavigationSort())->toBe(1);
});
