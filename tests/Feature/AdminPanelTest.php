<?php

use App\Enums\Role;
use App\Filament\Admin\Pages\ManageOrganiserPanel;
use App\Filament\Admin\Pages\ManageWelcome;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Policies\AdvisoryPolicy;
use App\Policies\ZaakPolicy;
use App\Settings\OrganiserPanelSettings;
use App\Settings\WelcomeSettings;
use Database\Seeders\OrganisationSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->municipality1 = Municipality::factory()->create([
        'name' => 'Municipality One',
    ]);

    $this->municipality2 = Municipality::factory()->create([
        'name' => 'Municipality Two',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $this->loginAdmin = User::factory()->create([
        'email' => 'adminlogin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
    ]);

    $this->municipalityAdmin = User::factory()->create([
        'email' => 'municadmin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);
    $this->municipality1->users()->attach($this->municipalityAdmin);

    $this->reviewer = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::Reviewer,
    ]);
    $this->municipality1->users()->attach($this->reviewer);

    // Create advisories
    $this->advisory1 = Advisory::factory()->create([
        'name' => 'Advisory One',
    ]);
    $this->advisory1->municipalities()->attach($this->municipality1);

    $this->advisory2 = Advisory::factory()->create([
        'name' => 'Advisory Two',
    ]);
    $this->advisory2->municipalities()->attach([$this->municipality1->id, $this->municipality2->id]);
});

test('municipality admin can access admin settings', function () {
    $this->actingAs($this->municipalityAdmin);

    expect(Settings::canAccess())->toBeTrue();
});

test('reviewer cannot access admin settings', function () {
    $this->actingAs($this->reviewer);

    expect(Settings::canAccess())->toBeFalse();
});

// TODO Lorenso
// test('municipality admin can only see municipality admins for their municipality', function () {
//     $this->actingAs($this->municipalityAdmin);

//     // MunicipalityAdmin should see scoped admin list (tenant-specific)
//     expect(AdminResource::isScopedToTenant())->toBeTrue();

//     Filament::setTenant($this->municipality1);

//     // Testing that when accessing the AdminResource, it's scoped to the current tenant
//     // We can't directly test the rendered content here, but we're verifying the scoping logic
//     expect(AdminResource::getTenantOwnershipRelationshipName())->toBe('municipalities');
// });

test('admin can edit municipalities', function () {
    $this->actingAs($this->admin);

    $this->assertTrue(app()->make(\App\Policies\MunicipalityPolicy::class)->update($this->admin, $this->municipality1));
});

test('municipality admin cannot edit municipalities', function () {
    $this->actingAs($this->municipalityAdmin);

    $this->assertFalse(app()->make(\App\Policies\MunicipalityPolicy::class)->update($this->municipalityAdmin, $this->municipality1));
});

test('admin can edit any advisory', function () {
    $this->actingAs($this->admin);

    $this->assertTrue(app()->make(AdvisoryPolicy::class)->update($this->admin, $this->advisory1));
    $this->assertTrue(app()->make(AdvisoryPolicy::class)->update($this->admin, $this->advisory2));
});

test('municipality admin can edit advisory only if they have access to all municipalities', function () {
    $this->actingAs($this->municipalityAdmin);

    // Advisory1 is associated only with municipality1, which this admin has access to
    $this->assertTrue(app()->make(AdvisoryPolicy::class)->update($this->municipalityAdmin, $this->advisory1));

    // Advisory2 is associated with both municipality1 and municipality2
    // This admin only has access to municipality1, so they can't edit it
    $this->assertFalse(app()->make(AdvisoryPolicy::class)->update($this->municipalityAdmin, $this->advisory2));

    // Now let's give them access to municipality2 as well
    $this->municipality2->users()->attach($this->municipalityAdmin);

    $this->municipalityAdmin->refresh();

    // Now they should be able to edit advisory2
    $this->assertTrue(app()->make(AdvisoryPolicy::class)->update($this->municipalityAdmin, $this->advisory2));
});

test('reviewer cannot edit advisories', function () {
    $this->actingAs($this->reviewer);

    $this->assertFalse(app()->make(AdvisoryPolicy::class)->update($this->reviewer, $this->advisory1));
});

test('2fa is enforced by default for admin panel', function () {

    expect(Filament::getPanel('admin')->hasMultiFactorAuthentication())
        ->toBeTrue();

    // TODO Lorenso
    //    $this->actingAs($this->loginAdmin);
    //
    //    // Ensure that the user is redirected to the 2FA setup page
    //    $this->get('admin')->assertRedirect();
});

test('only admin can access welcome page settings', function () {
    $this->actingAs($this->admin);
    expect(ManageWelcome::canAccess())->toBeTrue();

    $this->actingAs($this->municipalityAdmin);
    expect(ManageWelcome::canAccess())->toBeFalse();

    $this->actingAs($this->reviewer);
    expect(ManageWelcome::canAccess())->toBeFalse();
});

test('admin can update welcome page settings', function () {
    $this->actingAs($this->admin);

    livewire(ManageWelcome::class)->fillForm([
        'title' => 'New Title',
        'tagline' => 'New Tagline',
        'intro' => 'New Intro',
    ])->call('save');

    $settings = app(WelcomeSettings::class);
    expect($settings->title)->toBe('New Title');
    expect($settings->tagline)->toBe('New Tagline');
    expect($settings->intro)->toBe('<p>New Intro</p>');
});

test('admin can update organiser panel settings', function () {
    $this->actingAs($this->admin);

    livewire(ManageOrganiserPanel::class)->fillForm([
        'intro' => 'New Intro',
    ])->call('save');

    $settings = app(OrganiserPanelSettings::class);
    expect($settings->intro)->toBe('<p>New Intro</p>');

});

test('admin can access all zaken from all municipalities', function () {
    $this->seed(OrganisationSeeder::class);

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality1->id,
        'name' => 'Evenementenvergunning '.$this->municipality1->name,
        'is_active' => true,
    ]);

    $zaaktype2 = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality2->id,
        'name' => 'Evenementenvergunning '.$this->municipality2->name,
        'is_active' => true,
    ]);
    $zaken = Zaak::factory()
        ->count(2)
        ->for($zaaktype)
        ->create();

    $moreZaken = Zaak::factory()
        ->count(3)
        ->for($zaaktype2)
        ->create([]);
    $this->actingAs($this->admin);

    expect(app()->make(ZaakPolicy::class)->viewAny($this->admin))->toBeTrue();

    livewire(ListZaken::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Zaak::all());

});
