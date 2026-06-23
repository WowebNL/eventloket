<?php

declare(strict_types=1);

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Policies\ZaakPolicy;

beforeEach(function () {
    $this->policy = new ZaakPolicy;

    $this->municipality = Municipality::factory()->create();
    $this->otherMunicipality = Municipality::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->organisation = Organisation::factory()->create();

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);
});

// --- Organiser ---

test('organiser can view zaak belonging to own organisation', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Member]);

    expect($this->policy->view($organiser, $this->zaak))->toBeTrue();
});

test('organiser cannot view zaak belonging to another organisation', function () {
    $otherOrganisation = Organisation::factory()->create();
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $otherOrganisation->users()->attach($organiser, ['role' => OrganisationRole::Member]);

    expect($this->policy->view($organiser, $this->zaak))->toBeFalse();
});

// --- Municipality roles ---

test('reviewer in same municipality can view zaak', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->municipality);

    expect($this->policy->view($reviewer, $this->zaak))->toBeTrue();
});

test('reviewer in different municipality cannot view zaak', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->otherMunicipality);

    expect($this->policy->view($reviewer, $this->zaak))->toBeFalse();
});

test('municipality admin in same municipality can view zaak', function () {
    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $admin->municipalities()->attach($this->municipality);

    expect($this->policy->view($admin, $this->zaak))->toBeTrue();
});

test('municipality admin in different municipality cannot view zaak', function () {
    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $admin->municipalities()->attach($this->otherMunicipality);

    expect($this->policy->view($admin, $this->zaak))->toBeFalse();
});

test('reviewer municipality admin in same municipality can view zaak', function () {
    $admin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $admin->municipalities()->attach($this->municipality);

    expect($this->policy->view($admin, $this->zaak))->toBeTrue();
});

test('reviewer municipality admin in different municipality cannot view zaak', function () {
    $admin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $admin->municipalities()->attach($this->otherMunicipality);

    expect($this->policy->view($admin, $this->zaak))->toBeFalse();
});

// --- Advisor ---

test('advisor with active advice thread for zaak can view it', function () {
    $advisory = Advisory::factory()->create(['can_view_any_zaak' => false]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

    AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test advies',
        'advisory_id' => $advisory->id,
        'advice_status' => AdviceStatus::Asked,
    ]);

    expect($this->policy->view($advisor, $this->zaak))->toBeTrue();
});

test('advisor with only concept thread for zaak cannot view it', function () {
    $advisory = Advisory::factory()->create(['can_view_any_zaak' => false]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

    AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Concept advies',
        'advisory_id' => $advisory->id,
        'advice_status' => AdviceStatus::Concept,
    ]);

    expect($this->policy->view($advisor, $this->zaak))->toBeFalse();
});

test('advisor without any thread for zaak cannot view it', function () {
    $advisory = Advisory::factory()->create(['can_view_any_zaak' => false]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

    expect($this->policy->view($advisor, $this->zaak))->toBeFalse();
});

test('advisor from different advisory without thread cannot view zaak', function () {
    $advisoryWithThread = Advisory::factory()->create(['can_view_any_zaak' => false]);
    $otherAdvisory = Advisory::factory()->create(['can_view_any_zaak' => false]);

    AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test advies',
        'advisory_id' => $advisoryWithThread->id,
        'advice_status' => AdviceStatus::Asked,
    ]);

    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($otherAdvisory, ['role' => AdvisoryRole::Member]);

    expect($this->policy->view($advisor, $this->zaak))->toBeFalse();
});

test('advisor with can_view_any_zaak flag can view any zaak without a thread', function () {
    $advisory = Advisory::factory()->create(['can_view_any_zaak' => true]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

    expect($this->policy->view($advisor, $this->zaak))->toBeTrue();
});

// --- Admin ---

test('admin can view any zaak', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    expect($this->policy->view($admin, $this->zaak))->toBeTrue();
});
