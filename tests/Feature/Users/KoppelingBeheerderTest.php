<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use App\Models\User;
use App\Models\Users\KoppelingBeheerderUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

function koppelingBeheerder(?Municipality $municipality = null): KoppelingBeheerderUser
{
    $user = User::factory()->create(['role' => Role::KoppelingBeheerder]);

    if ($municipality !== null) {
        $municipality->users()->attach($user);
    }

    /** @var KoppelingBeheerderUser $fresh */
    $fresh = KoppelingBeheerderUser::findOrFail($user->id);

    return $fresh;
}

it('resolves the role to the KoppelingBeheerderUser subclass', function () {
    expect(User::resolveClassForRole(Role::KoppelingBeheerder))->toBe(KoppelingBeheerderUser::class);

    $user = User::factory()->create(['role' => Role::KoppelingBeheerder]);

    expect(User::find($user->id))->toBeInstanceOf(KoppelingBeheerderUser::class);
});

it('scopes the KoppelingBeheerderUser query to the role', function () {
    User::factory()->create(['role' => Role::KoppelingBeheerder]);
    User::factory()->create(['role' => Role::MunicipalityAdmin]);

    expect(KoppelingBeheerderUser::all())->toHaveCount(1)
        ->and(KoppelingBeheerderUser::first()->role)->toBe(Role::KoppelingBeheerder);
});

it('can access the municipality panel', function () {
    $user = koppelingBeheerder();

    expect($user->canAccessPanel(Filament::getPanel('municipality')))->toBeTrue();
});

it('can access the settings cluster but not the user-management resource', function () {
    $municipality = Municipality::factory()->create();
    $user = koppelingBeheerder($municipality);

    $this->actingAs($user);

    expect(Settings::canAccess())->toBeTrue()
        ->and(MunicipalityAdminUserResource::canAccess())->toBeFalse();
});

it('can view its own municipality zaken read-only but cannot handle them', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->for($municipality)->create();
    $zaak = Zaak::factory()->for($zaaktype)->create();

    $user = koppelingBeheerder($municipality);

    expect($user->can('viewAny', Zaak::class))->toBeTrue()
        ->and($user->can('view', $zaak))->toBeTrue()
        ->and($user->can('update', $zaak))->toBeFalse()
        ->and($user->can('uploadDocument', $zaak))->toBeFalse();
});

it('cannot view zaken of another municipality', function () {
    $own = Municipality::factory()->create();
    $other = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->for($other)->create();
    $zaak = Zaak::factory()->for($zaaktype)->create();

    $user = koppelingBeheerder($own);

    expect($user->can('view', $zaak))->toBeFalse();
});

it('can be invited and accepted as a koppeling beheerder', function () {
    $invite = MunicipalityInvite::create([
        'name' => 'Kees Koppeling',
        'email' => 'kees@example.com',
        'role' => Role::KoppelingBeheerder,
        'token' => Str::uuid(),
    ]);

    expect($invite->role)->toBe(Role::KoppelingBeheerder)
        ->and(User::resolveClassForRole($invite->role))->toBe(KoppelingBeheerderUser::class);
});
