<?php

declare(strict_types=1);

/**
 * Zaak::filterDocumentenForRole applies the configured vertrouwelijkheid
 * visibility per role, but an organiser must always see the documents they
 * submitted themselves (the aanvraag-PDF and bijlagen), even when those carry a
 * vertrouwelijkheid the organiser role is not configured to see.
 */

use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function documentWith(string $uuid, string $vertrouwelijkheid): Informatieobject
{
    return new Informatieobject(
        uuid: $uuid,
        url: "https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/{$uuid}",
        creatiedatum: '2026-07-02',
        titel: "Doc {$uuid}",
        vertrouwelijkheidaanduiding: $vertrouwelijkheid,
        auteur: 'Test',
        versie: 1,
        bestandsnaam: "{$uuid}.pdf",
        inhoud: '',
        beschrijving: null,
        informatieobjecttype: 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );
}

test('an organiser always sees their own submitted documents, even above their visible set', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $zaak = Zaak::factory()->create([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'organiser_user_id' => $organiser->id,
    ]);

    // The organiser submitted 'own-vertrouwelijk', a level the organiser role is
    // not configured to see.
    activity('document')
        ->event('created')
        ->causedBy($organiser)
        ->performedOn($zaak)
        ->withProperties(['document_uuid' => 'own-vertrouwelijk'])
        ->log('created');

    $documents = collect([
        documentWith('own-vertrouwelijk', 'vertrouwelijk'),      // the organiser's own → always visible
        documentWith('other-vertrouwelijk', 'vertrouwelijk'),    // same level but not theirs → hidden
        documentWith('case-confidential', 'zaakvertrouwelijk'),  // in the organiser's default visible set
        documentWith('public', 'openbaar'),                      // openbaar is visible to every role
    ]);

    $visible = $zaak->filterDocumentenForRole($documents, Role::Organiser)->pluck('uuid');

    expect($visible->all())->toEqualCanonicalizing(['own-vertrouwelijk', 'case-confidential', 'public']);
});

test('an openbaar document is visible to every role', function (Role $role) {
    // openbaar is the least confidential level there is, so a role that may see
    // zaakvertrouwelijk documents may certainly see public ones. Leaving it out
    // of the defaults meant a backend that labels its documents openbaar
    // (OneGround does this for system uploads) showed nothing to anyone.
    $zaak = Zaak::factory()->create([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
    ]);

    $visible = $zaak->filterDocumentenForRole(collect([documentWith('public', 'openbaar')]), $role);

    expect($visible->pluck('uuid')->all())->toBe(['public']);
})->with([
    'organiser' => Role::Organiser,
    'advisor' => Role::Advisor,
    'reviewer' => Role::Reviewer,
    'municipality admin' => Role::MunicipalityAdmin,
    'coordinator' => Role::Coordinator,
    'admin' => Role::Admin,
]);

test('a document above the role its clearance stays hidden', function () {
    // Allowing openbaar must not turn into "every role sees everything".
    $zaak = Zaak::factory()->create([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
    ]);

    $documents = collect([
        documentWith('confidential', 'vertrouwelijk'),
        documentWith('secret', 'geheim'),
    ]);

    expect($zaak->filterDocumentenForRole($documents, Role::Organiser))->toHaveCount(0);
});

test('a non-organiser role only follows the configured visibility, without an own-files exception', function () {
    $zaak = Zaak::factory()->create([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'organiser_user_id' => User::factory()->create(['role' => Role::Organiser])->id,
    ]);

    // Even if the organiser submitted a geheim document, a reviewer only sees
    // the levels configured for the reviewer role (geheim is not one).
    activity('document')
        ->event('created')
        ->causedBy(User::factory()->create(['role' => Role::Organiser]))
        ->performedOn($zaak)
        ->withProperties(['document_uuid' => 'organiser-geheim'])
        ->log('created');

    $documents = collect([
        documentWith('organiser-geheim', 'geheim'),
        documentWith('reviewer-visible', 'vertrouwelijk'),
    ]);

    $visible = $zaak->filterDocumentenForRole($documents, Role::Reviewer)->pluck('uuid');

    expect($visible->all())->toBe(['reviewer-visible']);
});
