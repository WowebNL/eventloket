<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);

    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin]);

    $this->zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
});

/**
 * Builds a zaak whose single document has an extensionless bestandsnaam,
 * mirroring the broken documents that already exist in production.
 */
function fakeZaakWithExtensionlessDocument(string $bestandsnaam, string $formaat): array
{
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentUrl = ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/no-ext';
    $contentUrl = ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/no-ext/download';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([
            [
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
                'zaak' => $zgwZaakUrl,
                'informatieobject' => $documentUrl,
            ],
        ]), 200),
        $documentUrl => Http::response([
            'url' => $documentUrl,
            'uuid' => 'no-ext',
            'identificatie' => 'DOC-NOEXT',
            'creatiedatum' => now()->format('Y-m-d'),
            'titel' => 'Extensieloos document',
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            'auteur' => 'Test',
            'versie' => 1,
            'bestandsnaam' => $bestandsnaam,
            'inhoud' => $contentUrl,
            'beschrijving' => '',
            'informatieobjecttype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
            'formaat' => $formaat,
            'locked' => false,
        ], 200),
        $contentUrl => Http::response('%PDF-1.4 raw pdf bytes', 200),
    ]);

    return [$zgwZaakUrl, $documentUrl];
}

test('view appends a derived extension to the filename and sets the content type', function () {
    fakeZaakWithExtensionlessDocument('Vergunningaanvraag', 'application/pdf');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $this->actingAs($this->organiser);

    $response = $this->get(route('zaak.documents.view', [
        'zaak' => $zaak->id,
        'documentuuid' => 'no-ext',
        'type' => 'view',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))
        ->toContain('inline')
        ->toContain('Vergunningaanvraag.pdf');
});

test('download serves an extensionless document as an attachment with extension', function () {
    fakeZaakWithExtensionlessDocument('Vergunningaanvraag', 'application/pdf');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $this->actingAs($this->organiser);

    $response = $this->get(route('zaak.documents.view', [
        'zaak' => $zaak->id,
        'documentuuid' => 'no-ext',
        'type' => 'download',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('Vergunningaanvraag.pdf');
});

test('a filename with non-ascii characters keeps the utf-8 name and gets an ascii fallback', function () {
    fakeZaakWithExtensionlessDocument('Vergunning Café Zürich.pdf', 'application/pdf');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $this->actingAs($this->organiser);

    $response = $this->get(route('zaak.documents.view', [
        'zaak' => $zaak->id,
        'documentuuid' => 'no-ext',
        'type' => 'download',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        // ASCII fallback for legacy clients (ö/é transliterated).
        ->toContain('filename="Vergunning Cafe Zurich.pdf"')
        // RFC 6266 UTF-8 name preserves the original characters for modern browsers.
        ->toContain("filename*=utf-8''Vergunning%20Caf%C3%A9%20Z%C3%BCrich.pdf");
});

test('a fully non-ascii filename falls back to a neutral document name', function () {
    // Empty formaat means no extension is reconstructed, so the bestandsnaam is
    // used as-is. Transliterating "日本語" yields nothing, exercising the default.
    fakeZaakWithExtensionlessDocument('日本語', '');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $this->actingAs($this->organiser);

    $response = $this->get(route('zaak.documents.view', [
        'zaak' => $zaak->id,
        'documentuuid' => 'no-ext',
        'type' => 'download',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        // A bare token without spaces is emitted unquoted by Symfony.
        ->toContain('filename=document')
        ->toContain("filename*=utf-8''%E6%97%A5%E6%9C%AC%E8%AA%9E");
});

test('an untrusted or empty formaat is served as octet-stream without inventing an extension', function () {
    fakeZaakWithExtensionlessDocument('Vergunningaanvraag', '');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $this->actingAs($this->organiser);

    $response = $this->get(route('zaak.documents.view', [
        'zaak' => $zaak->id,
        'documentuuid' => 'no-ext',
        'type' => 'view',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('application/octet-stream');
    expect($response->headers->get('Content-Disposition'))
        ->toContain('Vergunningaanvraag')
        ->not->toContain('Vergunningaanvraag.');
});
