<?php

use App\Enums\Role;
use App\Jobs\Zaak\CreateDocumentsZipJob;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\DocumentsZipReady;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->user = User::factory()->create(['role' => Role::Reviewer]);
    $this->zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
});

test('job sends DocumentsZipReady notification when zip is built', function () {
    Notification::fake();

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $documentUuid = 'test-doc-uuid';
    $doc = new Informatieobject(
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$documentUuid,
        uuid: $documentUuid,
        identificatie: 'DOC-001',
        bronorganisatie: '123',
        creatiedatum: now()->format('Y-m-d'),
        titel: 'Test document',
        vertrouwelijkheidaanduiding: 'zaakvertrouwelijk',
        auteur: 'Tester',
        status: null,
        taal: 'dut',
        bestandsnaam: 'test.pdf',
        bestandsomvang: 100,
        formaat: 'application/pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$documentUuid.'/download',
        link: null,
        beschrijving: '',
        versie: 1,
        indicatieGebruiksrecht: false,
        locked: false,
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
    );

    // Fake the documenten cache so the job can find the document
    Cache::put("zaak.{$zaak->id}.documenten", collect([$doc]));

    // Fake the raw document download from OpenZaak
    Http::fake([
        $doc->inhoud.'*' => Http::response('%PDF-1.4 test content', 200),
    ]);

    $job = new CreateDocumentsZipJob(
        zaak: $zaak,
        documentUuids: [$documentUuid],
        userId: $this->user->id,
    );

    $job->handle();

    Notification::assertSentTo($this->user, DocumentsZipReady::class);
});

test('buildZip returns a token and stores zip in cache', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $documentUuid = 'zip-doc-uuid';
    $doc = new Informatieobject(
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$documentUuid,
        uuid: $documentUuid,
        identificatie: 'DOC-ZIP',
        bronorganisatie: '123',
        creatiedatum: now()->format('Y-m-d'),
        titel: 'Zip document',
        vertrouwelijkheidaanduiding: 'zaakvertrouwelijk',
        auteur: 'Tester',
        status: null,
        taal: 'dut',
        bestandsnaam: 'zip.pdf',
        bestandsomvang: 100,
        formaat: 'application/pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$documentUuid.'/download',
        link: null,
        beschrijving: '',
        versie: 1,
        indicatieGebruiksrecht: false,
        locked: false,
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
    );

    Cache::put("zaak.{$zaak->id}.documenten", collect([$doc]));

    Http::fake([
        $doc->inhoud.'*' => Http::response('%PDF-1.4 zip content', 200),
    ]);

    $token = CreateDocumentsZipJob::buildZip($zaak, [$documentUuid], $this->user->id);

    expect($token)->not->toBeNull()
        ->and(Cache::has("document_zip.{$token}"))->toBeTrue()
        ->and(Cache::get("document_zip.{$token}")['zaak_id'])->toBe($zaak->id)
        ->and(Cache::get("document_zip.{$token}")['user_id'])->toBe($this->user->id);
});

test('buildZip strips path segments from a crafted bestandsnaam (zip slip)', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $documentUuid = 'slip-doc-uuid';
    $doc = new Informatieobject(
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$documentUuid,
        uuid: $documentUuid,
        identificatie: 'DOC-SLIP',
        bronorganisatie: '123',
        creatiedatum: now()->format('Y-m-d'),
        titel: 'Slip document',
        vertrouwelijkheidaanduiding: 'zaakvertrouwelijk',
        auteur: 'Tester',
        status: null,
        taal: 'dut',
        bestandsnaam: '../../../etc/evil.pdf',
        bestandsomvang: 100,
        formaat: 'application/pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$documentUuid.'/download',
        link: null,
        beschrijving: '',
        versie: 1,
        indicatieGebruiksrecht: false,
        locked: false,
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
    );

    Cache::put("zaak.{$zaak->id}.documenten", collect([$doc]));

    Http::fake([
        $doc->inhoud.'*' => Http::response('%PDF-1.4 slip content', 200),
    ]);

    $token = CreateDocumentsZipJob::buildZip($zaak, [$documentUuid], $this->user->id);

    $zip = new ZipArchive;
    $zip->open(storage_path("app/private/zips/{$token}.zip"));
    $entry = $zip->getNameIndex(0);
    $zip->close();

    // Only the basename survives; no directory traversal segments in the archive.
    expect($entry)->toBe('evil.pdf')
        ->and($entry)->not->toContain('..')
        ->and($entry)->not->toContain('/');
});

/**
 * Builds a document value object whose download url can be faked.
 */
function zipContentDocument(string $uuid, string $bestandsnaam, string $titel): Informatieobject
{
    return new Informatieobject(
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$uuid,
        uuid: $uuid,
        identificatie: 'DOC-CONTENT',
        bronorganisatie: '123',
        creatiedatum: now()->format('Y-m-d'),
        titel: $titel,
        vertrouwelijkheidaanduiding: 'zaakvertrouwelijk',
        auteur: 'Tester',
        status: null,
        taal: 'dut',
        bestandsnaam: $bestandsnaam,
        bestandsomvang: 100,
        formaat: 'application/pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$uuid.'/download',
        link: null,
        beschrijving: '',
        versie: 1,
        indicatieGebruiksrecht: false,
        locked: false,
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
    );
}

/**
 * @return array{0: array<int, string>, 1: string|false, 2: string|false, 3: string}
 */
function zipEntries(string $token, string $entryName): array
{
    $zip = new ZipArchive;
    $zip->open(storage_path("app/private/zips/{$token}.zip"));

    $names = [];
    $allContents = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
        $allContents .= $zip->getFromIndex($i);
    }

    $entry = $zip->getFromName($entryName);
    $notice = $zip->getFromName(__('shared/actions.download_documents.missing.file_name'));
    $zip->close();

    return [$names, $entry, $notice, $allContents];
}

test('buildZip writes the document bytes into the archive', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $doc = zipContentDocument('content-doc-uuid', 'plattegrond.pdf', 'Plattegrond');

    Cache::put("zaak.{$zaak->id}.documenten", collect([$doc]));

    Http::fake([
        $doc->inhoud.'*' => Http::response('%PDF-1.4 the real document', 200),
    ]);

    $token = CreateDocumentsZipJob::buildZip($zaak, ['content-doc-uuid'], $this->user->id);

    [$names, $entry, $notice] = zipEntries($token, 'plattegrond.pdf');

    expect($entry)->toBe('%PDF-1.4 the real document')
        ->and($names)->toBe(['plattegrond.pdf'])
        ->and($notice)->toBeFalse();
});

/**
 * The download endpoint answers about the media type when it cannot serve the
 * file, and that answer is not the document. It may never end up in the archive
 * under the document's own name; the archive says what is missing instead.
 */
test('buildZip keeps a refused download out of the archive and names it', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $refused = zipContentDocument('refused-doc-uuid', 'plattegrond.pdf', 'Plattegrond');
    $fine = zipContentDocument('fine-doc-uuid', 'draaiboek.pdf', 'Draaiboek');

    Cache::put("zaak.{$zaak->id}.documenten", collect([$refused, $fine]));

    Http::fake([
        $refused->inhoud.'*' => Http::response('application/octet-stream', 406),
        $fine->inhoud.'*' => Http::response('%PDF-1.4 the other document', 200),
    ]);

    $token = CreateDocumentsZipJob::buildZip($zaak, ['refused-doc-uuid', 'fine-doc-uuid'], $this->user->id);

    [$names, $entry, $notice, $allContents] = zipEntries($token, 'draaiboek.pdf');

    // What the endpoint answered instead of the file may not end up anywhere in
    // the archive, least of all under the document's own name.
    expect($allContents)->not->toContain('application/octet-stream')
        ->and($names)->not->toContain('plattegrond.pdf')
        ->and($entry)->toBe('%PDF-1.4 the other document')
        ->and($notice)->toBeString()
        ->and($notice)->toContain('plattegrond.pdf')
        ->and($notice)->not->toContain('draaiboek.pdf');
});

test('buildZip names a selected document that is no longer on the zaak', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $doc = zipContentDocument('present-doc-uuid', 'plattegrond.pdf', 'Plattegrond');

    Cache::put("zaak.{$zaak->id}.documenten", collect([$doc]));

    Http::fake([
        $doc->inhoud.'*' => Http::response('%PDF-1.4 the real document', 200),
    ]);

    $token = CreateDocumentsZipJob::buildZip($zaak, ['present-doc-uuid', 'gone-doc-uuid'], $this->user->id);

    [$names, , $notice] = zipEntries($token, 'plattegrond.pdf');

    expect($names)->toContain('plattegrond.pdf')
        ->and($notice)->toBeString()
        ->and($notice)->toContain('gone-doc-uuid');
});
