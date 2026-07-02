<?php

declare(strict_types=1);

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    // Geen zgw_zaak_url: documenten blijft leeg, geen ZGW-call nodig om de
    // kolomconfiguratie te kunnen testen.
    $this->zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
    ]);
});

test('organiser does not see the version column', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin->value]);

    $this->actingAs($organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->assertTableColumnHidden('versie');
});

test('reviewer does see the version column', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);

    $this->actingAs($reviewer);

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->assertTableColumnVisible('versie');
});

test('in submission-only mode the organiser cannot upload new files', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin->value]);

    $this->actingAs($organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak, 'submissionOnly' => true])
        ->assertTableActionHidden('upload');
});

test('outside submission-only mode the upload action is available', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin->value]);

    $this->actingAs($organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak, 'submissionOnly' => false])
        ->assertTableActionVisible('upload');
});
