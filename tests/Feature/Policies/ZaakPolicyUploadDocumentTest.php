<?php

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
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

test('an advisor with an advice question may only upload while the bestanden tab is shown', function (bool $tab, bool $readOnly, bool $expected) {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
        'show_bestanden_tab' => $tab,
        'show_bestanden_tab_read_only_for_advisories' => $readOnly,
    ]);
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => $municipality->id,
            'connection' => "gemeente_{$municipality->id}",
        ])->id,
    ]);

    $advisory = Advisory::factory()->create(['can_view_any_zaak' => true]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisory->users()->attach($advisor, ['role' => AdvisoryRole::Member]);

    AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Synthetische adviesvraag',
    ]);

    $policy = app()->make(ZaakPolicy::class);

    expect($policy->uploadDocument(User::query()->findOrFail($advisor->id), $zaak))->toBe($expected);
})->with([
    'tab shown' => [true, false, true],
    'tab off' => [false, false, false],
    'tab off, read-only for advisories' => [false, true, false],
]);
