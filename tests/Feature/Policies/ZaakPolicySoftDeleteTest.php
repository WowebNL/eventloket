<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Policies\ZaakPolicy;

beforeEach(function () {
    $this->policy = new ZaakPolicy;
    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->organisation = Organisation::factory()->create();
    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);
});

test('admin can delete zaak', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);

    expect($this->policy->delete($adminUser, $this->zaak))->toBeTrue();
});

test('municipality admin cannot delete zaak', function () {
    $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipalityAdmin->municipalities()->attach($this->municipality);

    expect($this->policy->delete($municipalityAdmin, $this->zaak))->toBeFalse();
});

test('reviewer municipality admin cannot delete zaak', function () {
    $reviewerMunicipalityAdmin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $reviewerMunicipalityAdmin->municipalities()->attach($this->municipality);

    expect($this->policy->delete($reviewerMunicipalityAdmin, $this->zaak))->toBeFalse();
});

test('reviewer cannot delete zaak', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->municipality);

    expect($this->policy->delete($reviewer, $this->zaak))->toBeFalse();
});

test('organiser cannot delete zaak', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Member]);

    expect($this->policy->delete($organiser, $this->zaak))->toBeFalse();
});

test('advisor cannot delete zaak', function () {
    $advisor = User::factory()->create(['role' => Role::Advisor]);

    expect($this->policy->delete($advisor, $this->zaak))->toBeFalse();
});

test('admin can restore zaak', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);

    expect($this->policy->restore($adminUser, $this->zaak))->toBeTrue();
});

test('municipality admin cannot restore zaak', function () {
    $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipalityAdmin->municipalities()->attach($this->municipality);

    expect($this->policy->restore($municipalityAdmin, $this->zaak))->toBeFalse();
});

test('reviewer cannot restore zaak', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->municipality);

    expect($this->policy->restore($reviewer, $this->zaak))->toBeFalse();
});

test('organiser cannot restore zaak', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Member]);

    expect($this->policy->restore($organiser, $this->zaak))->toBeFalse();
});

test('advisor cannot restore zaak', function () {
    $advisor = User::factory()->create(['role' => Role::Advisor]);

    expect($this->policy->restore($advisor, $this->zaak))->toBeFalse();
});

test('force delete is disabled for all users including admin', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);

    expect($this->policy->forceDelete($adminUser, $this->zaak))->toBeFalse()
        ->and($this->policy->forceDelete($municipalityAdmin, $this->zaak))->toBeFalse()
        ->and($this->policy->forceDelete($reviewer, $this->zaak))->toBeFalse();
});
