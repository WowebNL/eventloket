<?php

declare(strict_types=1);

/**
 * Zaak::filterDocumentenForRole applies the configured vertrouwelijkheid
 * visibility per role, but an organiser must always see the documents they
 * submitted themselves (the aanvraag-PDF and bijlagen), even when those are
 * uploaded as a vertrouwelijkheid the organiser role is not configured to see
 * (e.g. openbaar on an RX Mission connection).
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

test('an organiser always sees their own submitted documents, even when openbaar is not in the visible set', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $zaak = Zaak::factory()->create([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'organiser_user_id' => $organiser->id,
    ]);

    // The organiser submitted the openbaar document 'own-openbaar'.
    activity('document')
        ->event('created')
        ->causedBy($organiser)
        ->performedOn($zaak)
        ->withProperties(['document_uuid' => 'own-openbaar'])
        ->log('created');

    $documents = collect([
        documentWith('own-openbaar', 'openbaar'),      // the organiser's own → always visible
        documentWith('other-openbaar', 'openbaar'),    // openbaar but not theirs → hidden
        documentWith('case-confidential', 'zaakvertrouwelijk'), // in the organiser's default visible set
    ]);

    $visible = $zaak->filterDocumentenForRole($documents, Role::Organiser)->pluck('uuid');

    expect($visible->all())->toEqualCanonicalizing(['own-openbaar', 'case-confidential']);
});

test('a non-organiser role only follows the configured visibility, without an own-files exception', function () {
    $zaak = Zaak::factory()->create([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'organiser_user_id' => User::factory()->create(['role' => Role::Organiser])->id,
    ]);

    // Even if an openbaar document was submitted by the organiser, a reviewer
    // only sees the levels configured for the reviewer role (openbaar is not one).
    activity('document')
        ->event('created')
        ->causedBy(User::factory()->create(['role' => Role::Organiser]))
        ->performedOn($zaak)
        ->withProperties(['document_uuid' => 'organiser-openbaar'])
        ->log('created');

    $documents = collect([
        documentWith('organiser-openbaar', 'openbaar'),
        documentWith('reviewer-visible', 'vertrouwelijk'),
    ]);

    $visible = $zaak->filterDocumentenForRole($documents, Role::Reviewer)->pluck('uuid');

    expect($visible->all())->toBe(['reviewer-visible']);
});
