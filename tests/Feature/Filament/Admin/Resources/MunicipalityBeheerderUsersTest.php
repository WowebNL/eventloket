<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\MunicipalityResource\Pages\EditMunicipality;
use App\Filament\Admin\Resources\MunicipalityResource\RelationManagers\MunicipalityAdminUsersRelationManager;
use App\Filament\Shared\Resources\MunicipalityAdminUsers\Widgets\PendingMunicipalityAdminUserInvitesWidget;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->create(['role' => Role::Admin]));
});

test('the beheerders tab shows both municipality admins and koppelingbeheerders', function () {
    $municipality = Municipality::factory()->create();

    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $koppelingBeheerder = User::factory()->create(['role' => Role::KoppelingBeheerder]);
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $municipality->users()->attach([$admin->id, $koppelingBeheerder->id, $reviewer->id]);

    livewire(MunicipalityAdminUsersRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$admin, $koppelingBeheerder])
        ->assertCanNotSeeTableRecords([$reviewer]);
});

test('municipalityBeheerderUsers only returns admins and koppelingbeheerders', function () {
    $municipality = Municipality::factory()->create();

    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $koppelingBeheerder = User::factory()->create(['role' => Role::KoppelingBeheerder]);
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $municipality->users()->attach([$admin->id, $koppelingBeheerder->id, $reviewer->id]);

    $ids = $municipality->municipalityBeheerderUsers()->get()->pluck('id');

    expect($ids)->toContain($admin->id)
        ->toContain($koppelingBeheerder->id)
        ->not->toContain($reviewer->id);
});

test('a pending koppelingbeheerder invite shows in the pending invites widget', function () {
    $municipality = Municipality::factory()->create();

    $invite = MunicipalityInvite::create([
        'email' => 'kb@example.com',
        'role' => Role::KoppelingBeheerder,
        'token' => Str::uuid(),
    ]);
    $invite->municipalities()->attach($municipality);

    livewire(PendingMunicipalityAdminUserInvitesWidget::class, ['record' => $municipality])
        ->assertOk()
        ->assertCanSeeTableRecords([$invite]);
});
