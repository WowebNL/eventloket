<?php

declare(strict_types=1);

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Facades\Cache;
use Tests\Fakes\ZgwHttpFake;

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

function tableDocument(string $uuid, string $titel): Informatieobject
{
    return new Informatieobject(
        uuid: $uuid,
        url: 'https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobject/'.$uuid,
        creatiedatum: now()->toIso8601String(),
        titel: $titel,
        // Zaakvertrouwelijk is visible to organiser, reviewer and admin.
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        auteur: 'Test',
        versie: 1,
        bestandsnaam: $titel.'.pdf',
        inhoud: 'base64content',
        beschrijving: 'Test beschrijving',
        informatieobjecttype: 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );
}

function logDocCreated(User $creator, Zaak $zaak, string $documentUuid, ?string $filename = null): void
{
    $properties = ['document_uuid' => $documentUuid];
    if ($filename !== null) {
        $properties['filename'] = $filename;
    }

    activity('document')
        ->event('created')
        ->causedBy($creator)
        ->performedOn($zaak)
        ->withProperties($properties)
        ->log('created');
}

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

/**
 * Regression: a second visible() on the "Nieuwe versie" action would replace
 * the action's own visibility closure and discard the DocumentVersionAuthorizer
 * ownership check, making the button appear for everyone. These tests pin the
 * per-role, per-document visibility.
 */
function seedDocuments(Organisation $organisation, Zaak $zaak): User
{
    $owner = User::factory()->create(['role' => Role::Organiser]);
    $organisation->users()->attach($owner, ['role' => OrganisationRole::Admin->value]);

    // A zgw_zaak_url is required for the documenten accessor to read the cache;
    // the pre-populated cache below means no document ZGW call is made. The
    // wildcard fake covers the zaak-expand call the table render triggers.
    ZgwHttpFake::wildcardFake();
    $zaak->update(['zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1']);

    Cache::forever("zaak.{$zaak->id}.documenten", collect([
        tableDocument('own-doc-uuid', 'Eigen document'),
        tableDocument('aanvraagformulier-uuid', 'Aanvraagformulier'),
    ]));

    logDocCreated($owner, $zaak, 'own-doc-uuid');
    logDocCreated($owner, $zaak, 'aanvraagformulier-uuid', 'aanvraagformulier.pdf');

    return $owner;
}

test('an organiser sees "Nieuwe versie" only on their own document, not the aanvraagformulier', function () {
    $owner = seedDocuments($this->organisation, $this->zaak);

    $this->actingAs($owner);

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->assertTableActionVisible('new-version', 'own-doc-uuid')
        ->assertTableActionHidden('new-version', 'aanvraagformulier-uuid');
});

test('a reviewer does not see "Nieuwe versie" on an organiser-owned document', function () {
    seedDocuments($this->organisation, $this->zaak);

    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);

    $this->actingAs($reviewer);

    // Before the fix the override made this button visible to the reviewer too.
    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->assertTableActionHidden('new-version', 'own-doc-uuid');
});

test('a platform admin sees "Nieuwe versie" on every document including the aanvraagformulier', function () {
    seedDocuments($this->organisation, $this->zaak);

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin);

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->assertTableActionVisible('new-version', 'own-doc-uuid')
        ->assertTableActionVisible('new-version', 'aanvraagformulier-uuid');
});
