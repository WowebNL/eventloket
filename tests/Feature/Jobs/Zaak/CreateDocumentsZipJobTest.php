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
