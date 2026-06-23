<?php

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Policies\AdviceThreadPolicy;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    ZgwHttpFake::fakeStatustypen();
    ZgwHttpFake::wildcardFake();
    Notification::fake();

    $this->policy = new AdviceThreadPolicy;

    $this->municipality = Municipality::factory()->create();
    $this->otherMunicipality = Municipality::factory()->create();

    $this->advisory = Advisory::factory()->create();
    $this->otherAdvisory = Advisory::factory()->create();

    $this->organisation = Organisation::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    $this->thread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'title' => 'Test advice thread',
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

    it('allows advisor from same advisory when status is not Concept', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->advisories()->attach($this->advisory, ['role' => AdvisoryRole::Member]);

        expect($this->policy->view($advisor, $this->thread))->toBeTrue();
    });

    it('denies advisor from same advisory when status is Concept', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->advisories()->attach($this->advisory, ['role' => AdvisoryRole::Member]);

        $this->thread->update(['advice_status' => AdviceStatus::Concept]);

        expect($this->policy->view($advisor, $this->thread))->toBeFalse();
    });

    it('denies advisor from different advisory', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->advisories()->attach($this->otherAdvisory, ['role' => AdvisoryRole::Member]);

        expect($this->policy->view($advisor, $this->thread))->toBeFalse();
    });

    it('allows admin to view thread', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);

        expect($this->policy->view($admin, $this->thread))->toBeTrue();
    });

    it('denies organiser from viewing advice thread', function () {
        $organiser = User::factory()->create(['role' => Role::Organiser]);

        expect($this->policy->view($organiser, $this->thread))->toBeFalse();
    });
});
