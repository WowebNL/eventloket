<?php

declare(strict_types=1);

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Support\Documents\DocumentVersionAuthorizer;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Tests\Fakes\ZgwHttpFake;

/**
 * The thread attach flow ("Nieuwe versie van bestaand bestand") must honour the
 * same per-document ownership rule as the document table: only documents the
 * user is allowed to version may be selected, and the aanvraagformulier /
 * ownerless documents are off-limits to non-admins.
 */
function makeThreadDocument(string $uuid, string $titel): Informatieobject
{
    return new Informatieobject(
        uuid: $uuid,
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$uuid,
        creatiedatum: now()->toIso8601String(),
        titel: $titel,
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        auteur: 'Test',
        versie: 1,
        bestandsnaam: $titel.'.pdf',
        inhoud: 'base64content',
        beschrijving: 'Test beschrijving',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );
}

function logCreated(User $creator, Zaak $zaak, string $documentUuid, ?string $filename = null): void
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

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();
    $this->organisation = Organisation::factory()->create(['type' => 'business']);

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin]);

    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    ZgwHttpFake::wildcardFake();

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    // One document owned by the organiser, one system aanvraagformulier.
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect([
        makeThreadDocument('own-doc-uuid', 'Eigen document'),
        makeThreadDocument('aanvraagformulier-uuid', 'Aanvraagformulier'),
    ]));

    logCreated($this->organiser, $this->zaak, 'own-doc-uuid');
    logCreated($this->organiser, $this->zaak, 'aanvraagformulier-uuid', 'aanvraagformulier.pdf');

    $this->thread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test thread',
    ]);

    Message::forceCreate([
        'thread_id' => $this->thread->id,
        'user_id' => $this->organiser->id,
        'body' => 'Initial message',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);
});

test('the existing-document select only offers documents the user may version', function () {
    // The Select options and the action's server-side guard both derive from
    // this exact filter. Exercising it against real cached documenten + their
    // activity-log creators validates the whole plumbing the fix depends on.
    $selectable = $this->zaak->documenten
        ->filter(fn ($document): bool => DocumentVersionAuthorizer::canAddVersion($this->organiser, $this->zaak, $document->uuid))
        ->pluck('uuid');

    expect($selectable)->toContain('own-doc-uuid')
        ->and($selectable)->not->toContain('aanvraagformulier-uuid');
});

test('the organiser may version their own document but not the system aanvraagformulier', function () {
    expect(DocumentVersionAuthorizer::canAddVersion($this->organiser, $this->zaak, 'own-doc-uuid'))->toBeTrue()
        ->and(DocumentVersionAuthorizer::canAddVersion($this->organiser, $this->zaak, 'aanvraagformulier-uuid'))->toBeFalse();
});

test('a reviewer from the municipality may not version the organiser-owned document', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);

    // Cross-group: a municipality user is not in the organiser's group, so the
    // thread attach flow must not offer or accept the organiser-owned document.
    expect(DocumentVersionAuthorizer::canAddVersion($reviewer, $this->zaak, 'own-doc-uuid'))->toBeFalse();
});
