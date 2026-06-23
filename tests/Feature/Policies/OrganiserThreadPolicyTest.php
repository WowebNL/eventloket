<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Policies\OrganiserThreadPolicy;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    ZgwHttpFake::fakeStatustypen();
    ZgwHttpFake::wildcardFake();
    Notification::fake();

    $this->policy = new OrganiserThreadPolicy;

    $this->municipality = Municipality::factory()->create();
    $this->otherMunicipality = Municipality::factory()->create();

    $this->organisation = Organisation::factory()->create();
    $this->otherOrganisation = Organisation::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    $this->thread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'title' => 'Test thread',
    ]);
});

describe('view', function () {
    it('allows reviewer from same municipality to view thread', function () {
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $reviewer->municipalities()->attach($this->municipality);

        expect($this->policy->view($reviewer, $this->thread))->toBeTrue();
    });

    it('denies reviewer from different municipality', function () {
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $reviewer->municipalities()->attach($this->otherMunicipality);

        expect($this->policy->view($reviewer, $this->thread))->toBeFalse();
    });

    it('allows municipality admin from same municipality to view thread', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->municipality);

        expect($this->policy->view($municipalityAdmin, $this->thread))->toBeTrue();
    });

    it('denies municipality admin from different municipality', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->otherMunicipality);

        expect($this->policy->view($municipalityAdmin, $this->thread))->toBeFalse();
    });

    it('allows organiser from same organisation to view thread', function () {
        $organiser = User::factory()->create(['role' => Role::Organiser]);
        $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Member]);

        expect($this->policy->view($organiser, $this->thread))->toBeTrue();
    });

    it('denies organiser from different organisation', function () {
        $organiser = User::factory()->create(['role' => Role::Organiser]);
        $this->otherOrganisation->users()->attach($organiser, ['role' => OrganisationRole::Member]);

        expect($this->policy->view($organiser, $this->thread))->toBeFalse();
    });

    it('allows admin to view thread', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);

        expect($this->policy->view($admin, $this->thread))->toBeTrue();
    });

    it('denies advisor from viewing organiser thread', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->view($advisor, $this->thread))->toBeFalse();
    });
});
