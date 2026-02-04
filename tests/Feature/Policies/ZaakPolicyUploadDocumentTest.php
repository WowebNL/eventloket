<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Policies\ZaakPolicy;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create([
        'type' => OrganisationType::Business,
    ]);

    $this->otherOrganisation = Organisation::factory()->create([
        'type' => OrganisationType::Business,
    ]);

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin->value]);

    $this->municipality = Municipality::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);
});

test('organiser can upload document for accessible organisation', function () {
    $policy = app()->make(ZaakPolicy::class);

    expect($this->organiser->canAccessOrganisation($this->organisation->id))->toBeTrue()
        ->and($policy->uploadDocument($this->organiser, $this->zaak))->toBeTrue();
});

test('organiser cannot upload document for inaccessible organisation', function () {
    $policy = app()->make(ZaakPolicy::class);

    $otherOrganiser = User::factory()->create(['role' => Role::Organiser]);
    $this->otherOrganisation->users()->attach($otherOrganiser, ['role' => OrganisationRole::Admin->value]);

    expect($otherOrganiser->canAccessOrganisation($this->organisation->id))->toBeFalse()
        ->and($policy->uploadDocument($otherOrganiser, $this->zaak))->toBeFalse();
});
