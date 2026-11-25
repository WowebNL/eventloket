<?php

declare(strict_types=1);

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

/*
 * In submission-only mode (the organiser, documents tab switched off) the
 * documents table may only tell the organiser about the files delivered with
 * the application. The notices about documents that could not be read and the
 * empty states are decided on the whole zaak, so they stay out of this mode.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/**
 * A zaak of a fresh organisation whose document list resolves, with one entry
 * per spec: `['bestandsnaam' => ...]` for a document that can be read,
 * `['refused' => true]` for one the API is not authorised to hand over, and
 * `['unavailable' => true]` for one it fails to produce.
 *
 * @param  list<array<string, mixed>>  $documents
 * @return array{0: Zaak, 1: User}
 */
function submissionOnlyTableZaak(array $documents): array
{
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $links = [];

    foreach ($documents as $index => $document) {
        $uuid = (string) ($index + 1);
        $docUrl = ZgwHttpFake::documentUrl($uuid);

        if ($document['refused'] ?? false) {
            Http::fake([$docUrl => Http::response(['code' => 'permission_denied', 'status' => 403], 403)]);
        } elseif ($document['unavailable'] ?? false) {
            Http::fake([$docUrl => Http::response(['code' => 'error', 'status' => 500], 500)]);
        } else {
            ZgwHttpFake::fakeSingleDocument($uuid, [
                'titel' => 'Titel '.$document['bestandsnaam'],
                'bestandsnaam' => $document['bestandsnaam'],
            ]);
        }

        $links[] = [
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/'.$uuid,
            'zaak' => $zaakUrl,
            'informatieobject' => $docUrl,
        ];
    }

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope($links), 200),
        // The document-type column resolves the zaaktype's document types; not
        // what these tests are about, so it stays empty.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([]), 200),
    ]);

    $organisation = Organisation::factory()->create(['type' => OrganisationType::Business]);
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin]);

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'organisation_id' => $organisation->id,
        'zaaktype_id' => Zaaktype::factory()->for(Municipality::factory())->create()->id,
    ]);

    return [$zaak, User::query()->findOrFail($organiser->id)];
}

test('no notice about unreadable documents in submission-only mode, also not in the component state', function () {
    [$zaak, $organiser] = submissionOnlyTableZaak([
        ['bestandsnaam' => 'aanvraagformulier.pdf'],
        ['bestandsnaam' => 'gemeentenotitie.pdf'],
        ['unavailable' => true],
        ['refused' => true],
    ]);

    $this->actingAs($organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak, 'submissionOnly' => true])
        ->assertSee('Titel aanvraagformulier.pdf')
        ->assertDontSee('Titel gemeentenotitie.pdf')
        ->assertDontSee('kan nu niet worden getoond')
        ->assertDontSee('Niet beschikbaar via deze koppeling')
        ->assertSet('unavailableDocumentCount', 0)
        ->assertSet('hasForbiddenDocuments', false)
        ->assertSet('hasDocuments', true);
});

test('an empty submission-only table does not tell that the zaak holds other documents', function (array $documents) {
    [$zaak, $organiser] = submissionOnlyTableZaak($documents);

    $this->actingAs($organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak, 'submissionOnly' => true])
        ->assertSee('Een ogenblik geduld, de bestanden van de aanvraag komen zometeen beschikbaar')
        ->assertDontSee('Geen bestanden om te tonen')
        ->assertDontSee('Bij deze aanvraag zijn geen aanvraagdocumenten ingediend')
        ->assertDontSee('Deze zaak bevat wel bestanden')
        ->assertDontSee('De bestanden kunnen nu niet worden getoond')
        ->assertDontSee('De bestanden zijn niet beschikbaar via deze koppeling')
        ->assertSet('hasDocuments', false);
})->with([
    'only a municipal document' => [[['bestandsnaam' => 'gemeentenotitie.pdf']]],
    'only an unreadable document' => [[['unavailable' => true]]],
    'only a refused document' => [[['refused' => true]]],
]);

test('outside submission-only mode the notices and empty states are unchanged', function () {
    [$zaak] = submissionOnlyTableZaak([
        ['bestandsnaam' => 'aanvraagformulier.pdf'],
        ['unavailable' => true],
        ['refused' => true],
    ]);
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);

    $this->actingAs($reviewer);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('Eén bestand kan nu niet worden getoond')
        ->assertSee('Niet beschikbaar via deze koppeling')
        ->assertSet('unavailableDocumentCount', 1)
        ->assertSet('hasForbiddenDocuments', true);
});
