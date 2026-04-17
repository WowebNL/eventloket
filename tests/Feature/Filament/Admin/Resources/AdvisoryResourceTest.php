<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\AdvisoryResource;
use App\Filament\Admin\Resources\AdvisoryResource\Pages\CreateAdvisory;
use App\Filament\Admin\Resources\AdvisoryResource\Pages\EditAdvisory;
use App\Filament\Admin\Resources\AdvisoryResource\Pages\ListAdvisories;
use App\Filament\Admin\Resources\AdvisoryResource\RelationManagers\MunicipalitiesRelationManager;
use App\Filament\Admin\Resources\AdvisoryResource\RelationManagers\UsersRelationManager;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

covers(
    AdvisoryResource::class,
    ListAdvisories::class,
    CreateAdvisory::class,
    EditAdvisory::class,
    MunicipalitiesRelationManager::class,
    UsersRelationManager::class,
);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($this->admin);
});

test('admin can view advisory list', function () {
    $advisories = Advisory::factory()->count(3)->create();

    livewire(ListAdvisories::class)
        ->assertOk()
        ->assertCanSeeTableRecords($advisories);
});

test('admin can create advisory', function () {
    livewire(CreateAdvisory::class)
        ->fillForm([
            'name' => 'Test Advisory Service',
            'can_view_any_zaak' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Advisory::where('name', 'Test Advisory Service')->exists())->toBeTrue();
});

test('advisory form validates name is required', function () {
    livewire(CreateAdvisory::class)
        ->fillForm([
            'name' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

test('advisory form validates name max length', function () {
    livewire(CreateAdvisory::class)
        ->fillForm([
            'name' => str_repeat('a', 256),
        ])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

test('admin can edit advisory', function () {
    $advisory = Advisory::factory()->create([
        'name' => 'Original Advisory',
        'can_view_any_zaak' => false,
    ]);

    livewire(EditAdvisory::class, [
        'record' => $advisory->id,
    ])
        ->fillForm([
            'name' => 'Updated Advisory',
            'can_view_any_zaak' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($advisory->fresh()->name)->toBe('Updated Advisory')
        ->and($advisory->fresh()->can_view_any_zaak)->toBeTrue();
});

test('admin can delete advisory from edit page', function () {
    $advisory = Advisory::factory()->create();

    livewire(EditAdvisory::class, [
        'record' => $advisory->id,
    ])
        ->callAction(DeleteAction::class);

    $this->assertSoftDeleted('advisories', ['id' => $advisory->id]);
});

test('advisory table has name column that is searchable', function () {
    Advisory::factory()->create(['name' => 'Alpha Advisory']);
    Advisory::factory()->create(['name' => 'Beta Advisory']);

    $alpha = Advisory::where('name', 'Alpha Advisory')->first();
    $beta = Advisory::where('name', 'Beta Advisory')->first();

    livewire(ListAdvisories::class)
        ->searchTable('Alpha')
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);
});

test('advisory table has can_view_any_zaak icon column', function () {
    livewire(ListAdvisories::class)
        ->assertTableColumnExists('can_view_any_zaak');
});

test('advisory table has municipalities name column', function () {
    livewire(ListAdvisories::class)
        ->assertTableColumnExists('municipalities.name');
});

test('advisory table has edit action', function () {
    $advisory = Advisory::factory()->create();

    livewire(ListAdvisories::class)
        ->assertTableActionExists('edit', record: $advisory);
});

test('admin can access municipalities relation manager', function () {
    $advisory = Advisory::factory()->create();

    livewire(MunicipalitiesRelationManager::class, [
        'ownerRecord' => $advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->assertOk();
});

test('admin can attach municipality to advisory', function () {
    $advisory = Advisory::factory()->create();
    $municipality = Municipality::factory()->create(['name' => 'Test Municipality']);

    livewire(MunicipalitiesRelationManager::class, [
        'ownerRecord' => $advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->callTableAction('attach', data: [
            'recordId' => [$municipality->id],
        ])
        ->assertHasNoActionErrors();

    expect($advisory->municipalities->contains($municipality))->toBeTrue();
});

test('admin can access users relation manager', function () {
    $advisory = Advisory::factory()->create();

    livewire(UsersRelationManager::class, [
        'ownerRecord' => $advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->assertOk();
});

test('admin can attach advisor user to advisory', function () {
    $advisory = Advisory::factory()->create();
    $advisorUser = User::factory()->create(['role' => Role::Advisor]);

    livewire(UsersRelationManager::class, [
        'ownerRecord' => $advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->callTableAction('attach', data: [
            'recordId' => [$advisorUser->id],
        ])
        ->assertHasNoActionErrors();

    expect($advisory->users->contains($advisorUser))->toBeTrue();
});

test('advisory resource has correct model', function () {
    expect(AdvisoryResource::getModel())->toBe(Advisory::class);
});

test('advisory resource has correct navigation icon', function () {
    expect(AdvisoryResource::getNavigationIcon())->toBe('heroicon-o-lifebuoy');
});

test('advisory resource has correct navigation sort', function () {
    expect(AdvisoryResource::getNavigationSort())->toBe(3);
});

test('admin can see trashed filter on advisory list', function () {
    livewire(ListAdvisories::class)
        ->assertTableFilterExists('trashed');
});

test('admin can restore soft-deleted advisory', function () {
    $advisory = Advisory::factory()->create();
    $advisory->delete();

    livewire(ListAdvisories::class)
        ->filterTable('trashed', ['value' => 'with'])
        ->assertCanSeeTableRecords([$advisory], true)
        ->callTableAction('restore', $advisory);

    expect(Advisory::find($advisory->id))->not->toBeNull();
});
