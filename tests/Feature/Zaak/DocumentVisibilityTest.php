<?php

declare(strict_types=1);

/**
 * The status half of document visibility: may this document be shown at all,
 * regardless of who is asking. The role half (vertrouwelijkheidaanduiding
 * weighed against the user's role) is covered by ZaakDocumentVisibilityTest,
 * which calls filterDocumentenForRole directly. It cannot be exercised through
 * the `documenten` accessor, which skips the role filter while running in
 * console.
 *
 * The scenarios mirror a zaak closed on a OneGround (RX Mission) connection:
 * closing archives every document immediately, which used to make the whole
 * file list disappear from a completed permit.
 */

use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

/**
 * @param  list<array<string, mixed>>  $documents
 */
function zaakMetDocumenten(array $documents): Zaak
{
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();

    $links = [];
    foreach ($documents as $index => $document) {
        $uuid = (string) ($index + 1);
        $docUrl = ZgwHttpFake::fakeSingleDocument($uuid, $document);
        $links[] = [
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/'.$uuid,
            'zaak' => $zaakUrl,
            'informatieobject' => $docUrl,
        ];
    }

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope($links), 200),
    ]);

    return Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => Zaaktype::factory()->for(Municipality::factory())->create()->id,
    ]);
}

test('an archived document stays visible', function () {
    // Archived means frozen for archival purposes, not withdrawn. Hiding it made
    // the permit and its attachments vanish the moment the zaak was closed.
    $zaak = zaakMetDocumenten([
        ['status' => 'gearchiveerd', 'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk'],
    ]);

    expect($zaak->documenten)->toHaveCount(1);
});

test('a concept document stays hidden', function (string $status) {
    $zaak = zaakMetDocumenten([
        ['status' => $status, 'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk'],
    ]);

    expect($zaak->documenten)->toHaveCount(0);
})->with([
    'in_bewerking' => 'in_bewerking',
    'ter_vaststelling' => 'ter_vaststelling',
]);

test('a closed OneGround zaak keeps showing its documents', function () {
    $zaak = zaakMetDocumenten([
        ['status' => 'gearchiveerd', 'vertrouwelijkheidaanduiding' => 'openbaar', 'titel' => 'Aanvraagformulier'],
        ['status' => 'gearchiveerd', 'vertrouwelijkheidaanduiding' => 'openbaar', 'titel' => 'Plattegrond'],
    ]);

    expect($zaak->documenten)->toHaveCount(2);
});
