<?php

use App\Enums\MunicipalityVariableType;
use App\Enums\Role;
use App\Filament\Admin\Resources\MunicipalityVariables\MunicipalityVariableResource;
use App\Filament\Admin\Resources\MunicipalityVariables\Pages\CreateMunicipalityVariable;
use App\Filament\Admin\Resources\MunicipalityVariables\Pages\EditMunicipalityVariable;
use App\Filament\Admin\Resources\MunicipalityVariables\Pages\ListMunicipalityVariables;
use App\Models\MunicipalityVariable;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

covers(
    MunicipalityVariableResource::class,
    ListMunicipalityVariables::class,
    CreateMunicipalityVariable::class,
    EditMunicipalityVariable::class,
);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($this->admin);
});

test('admin can view municipality variable list', function () {
    // Admin resource only shows global (municipality_id = null) variables
    $globalVariables = MunicipalityVariable::factory()
        ->defaultTemplate()
        ->count(3)
        ->create();

    livewire(ListMunicipalityVariables::class)
        ->assertOk()
        ->assertCanSeeTableRecords($globalVariables);
});

test('admin municipality variable list only shows global variables', function () {
    $globalVariable = MunicipalityVariable::factory()->defaultTemplate()->create([
        'name' => 'Global Variable',
    ]);

    // Municipality-specific variable should not appear in admin list
    $municipalityVariable = MunicipalityVariable::factory()->create([
        'name' => 'Municipality Specific Variable',
    ]);

    livewire(ListMunicipalityVariables::class)
        ->assertCanSeeTableRecords([$globalVariable])
        ->assertCanNotSeeTableRecords([$municipalityVariable]);
});

test('admin can create global municipality variable with text type', function () {
    livewire(CreateMunicipalityVariable::class)
        ->fillForm([
            'name' => 'Test Variable',
            'key' => 'test_variable',
            'type' => MunicipalityVariableType::Text,
            'value' => 'Test Value',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MunicipalityVariable::where('name', 'Test Variable')->where('municipality_id', null)->exists())->toBeTrue();
});

test('municipality variable form validates name is required', function () {
    livewire(CreateMunicipalityVariable::class)
        ->fillForm([
            'name' => '',
            'key' => 'test_key',
            'type' => MunicipalityVariableType::Text,
            'value' => 'Test Value',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

test('municipality variable form validates key is required', function () {
    livewire(CreateMunicipalityVariable::class)
        ->fillForm([
            'name' => 'Test Name',
            'key' => '',
            'type' => MunicipalityVariableType::Text,
            'value' => 'Test Value',
        ])
        ->call('create')
        ->assertHasFormErrors(['key' => 'required']);
});

test('municipality variable table has name and key columns', function () {
    livewire(ListMunicipalityVariables::class)
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('key');
});

test('municipality variable table has type column', function () {
    livewire(ListMunicipalityVariables::class)
        ->assertTableColumnExists('type');
});

test('municipality variable table allows searching by name', function () {
    $alpha = MunicipalityVariable::factory()->defaultTemplate()->create(['name' => 'Alpha Variable']);
    $beta = MunicipalityVariable::factory()->defaultTemplate()->create(['name' => 'Beta Variable']);

    livewire(ListMunicipalityVariables::class)
        ->searchTable('Alpha')
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);
});

test('admin can edit municipality variable value', function () {
    $variable = MunicipalityVariable::factory()->defaultTemplate()->create([
        'name' => 'Original Name',
        'type' => MunicipalityVariableType::Text,
        'value' => 'Original Value',
    ]);

    livewire(EditMunicipalityVariable::class, [
        'record' => $variable->id,
    ])
        ->fillForm([
            'value' => 'Updated Value',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($variable->fresh()->value)->toBe('Updated Value');
});

test('municipality variable table has edit action', function () {
    $variable = MunicipalityVariable::factory()->defaultTemplate()->create();

    livewire(ListMunicipalityVariables::class)
        ->assertTableActionExists('edit', record: $variable);
});

test('municipality variable resource has correct model', function () {
    expect(MunicipalityVariableResource::getModel())->toBe(MunicipalityVariable::class);
});
