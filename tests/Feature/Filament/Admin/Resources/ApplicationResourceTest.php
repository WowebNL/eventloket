<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\ApplicationResource;
use App\Filament\Admin\Resources\ApplicationResource\Pages\CreateApplication;
use App\Filament\Admin\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Admin\Resources\ApplicationResource\Pages\ListApplications;
use App\Filament\Admin\Resources\ApplicationResource\RelationManagers\ClientsRelationManager;
use App\Filament\Admin\Resources\ApplicationResource\RelationManagers\TokensRelationManager;
use App\Models\Application;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

covers(
    ApplicationResource::class,
    ListApplications::class,
    CreateApplication::class,
    EditApplication::class,
    ClientsRelationManager::class,
    TokensRelationManager::class,
);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($this->admin);
});

test('admin can view application list', function () {
    $applications = Application::factory()->count(3)->create();

    livewire(ListApplications::class)
        ->assertOk()
        ->assertCanSeeTableRecords($applications);
});

test('admin can create application', function () {
    livewire(CreateApplication::class)
        ->fillForm([
            'name' => 'Test Application',
            'all_endpoints' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Application::where('name', 'Test Application')->exists())->toBeTrue();
});

test('application form validates name is required', function () {
    livewire(CreateApplication::class)
        ->fillForm([
            'name' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

test('application form validates name max length', function () {
    livewire(CreateApplication::class)
        ->fillForm([
            'name' => str_repeat('a', 256),
        ])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

test('admin can edit application', function () {
    $application = Application::factory()->create([
        'name' => 'Original Name',
        'all_endpoints' => false,
    ]);

    livewire(EditApplication::class, [
        'record' => $application->uuid,
    ])
        ->fillForm([
            'name' => 'Updated Name',
            'all_endpoints' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $application->refresh();

    expect($application->name)->toBe('Updated Name')
        ->and($application->all_endpoints)->toBe(1);
});

test('admin can delete application from edit page', function () {
    $application = Application::factory()->create();

    livewire(EditApplication::class, [
        'record' => $application->uuid,
    ])
        ->callAction(DeleteAction::class);

    expect(Application::find($application->id))->toBeNull();
});

test('admin can bulk delete applications', function () {
    $applications = Application::factory()->count(3)->create();

    livewire(ListApplications::class)
        ->callTableBulkAction('delete', $applications);

    foreach ($applications as $application) {
        expect(Application::find($application->id))->toBeNull();
    }
});

test('application table has name column that is searchable', function () {
    Application::factory()->create(['name' => 'Alpha Application']);
    Application::factory()->create(['name' => 'Beta Application']);

    $alpha = Application::where('name', 'Alpha Application')->first();
    $beta = Application::where('name', 'Beta Application')->first();

    livewire(ListApplications::class)
        ->searchTable('Alpha')
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);
});

test('application table has all_endpoints icon column', function () {
    livewire(ListApplications::class)
        ->assertTableColumnExists('all_endpoints');
});

test('application table has created_at and updated_at columns', function () {
    livewire(ListApplications::class)
        ->assertTableColumnExists('created_at')
        ->assertTableColumnExists('updated_at');
});

test('application table has edit action', function () {
    $application = Application::factory()->create();

    livewire(ListApplications::class)
        ->assertTableActionExists('edit', record: $application);
});

test('admin can access clients relation manager', function () {
    $application = Application::factory()->create();

    livewire(ClientsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => EditApplication::class,
    ])
        ->assertOk();
});

test('admin can access tokens relation manager', function () {
    $application = Application::factory()->create();

    livewire(TokensRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => EditApplication::class,
    ])
        ->assertOk();
});

test('application resource has correct model', function () {
    expect(ApplicationResource::getModel())->toBe(Application::class);
});

test('application resource has correct navigation icon', function () {
    expect(ApplicationResource::getNavigationIcon())->toBe('heroicon-o-key');
});
