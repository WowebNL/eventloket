<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Jobs\Zaak\UploadDocumentsJob;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
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

test('job uploads each file to OpenZaak and links it to the zaak', function () {
    Storage::fake('local');
    Storage::put('documents/file1.pdf', '%PDF-1.4 first');
    Storage::put('documents/file2.pdf', '%PDF-1.4 second');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::sequence()
            ->push([
                'url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-1',
                'uuid' => 'doc-1',
                'identificatie' => 'DOC-001',
                'titel' => 'Eerste document',
                'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
                'auteur' => $this->organiser->name,
                'versie' => 1,
                'bestandsnaam' => 'file1.pdf',
                'inhoud' => '',
                'beschrijving' => '',
                'formaat' => 'application/pdf',
                'locked' => false,
                'bestandsgrootte' => 0,
                'creatiedatum' => now()->format('Y-m-d'),
                'wijzigingsdatum' => now()->toIso8601String(),
                'informatieobjecttype' => $documentTypeUrl,
                'indicatieGebruiksrecht' => false,
            ], 201)
            ->push([
                'url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-2',
                'uuid' => 'doc-2',
                'identificatie' => 'DOC-002',
                'titel' => 'Tweede document',
                'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
                'auteur' => $this->organiser->name,
                'versie' => 1,
                'bestandsnaam' => 'file2.pdf',
                'inhoud' => '',
                'beschrijving' => '',
                'formaat' => 'application/pdf',
                'locked' => false,
                'bestandsgrootte' => 0,
                'creatiedatum' => now()->format('Y-m-d'),
                'wijzigingsdatum' => now()->toIso8601String(),
                'informatieobjecttype' => $documentTypeUrl,
                'indicatieGebruiksrecht' => false,
            ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
            'zaak' => $zgwZaakUrl,
            'informatieobject' => '',
        ], 201),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $job = new UploadDocumentsJob(
        zaak: $zaak,
        files: [
            ['path' => 'documents/file1.pdf', 'titel' => 'Eerste document', 'original_name' => 'file1.pdf', 'informatieobjecttype' => $documentTypeUrl],
            ['path' => 'documents/file2.pdf', 'titel' => 'Tweede document', 'original_name' => 'file2.pdf', 'informatieobjecttype' => $documentTypeUrl],
        ],
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        userId: $this->organiser->id,
    );

    $job->handle();

    Http::assertSentCount(5); // 1 openzaak fetch + 2 document stores + 2 zaak links

    Http::assertSent(fn ($request) => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['titel'] === 'Eerste document'
    );

    Http::assertSent(fn ($request) => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['titel'] === 'Tweede document'
    );

    // Every stored document carries the finalised 'definitief' status.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['status'] === 'definitief'
    );
});

test('job clears the documenten cache after uploading', function () {
    Storage::fake('local');
    Storage::put('documents/file1.pdf', '%PDF-1.4 content');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-cache',
            'uuid' => 'doc-cache',
            'identificatie' => 'DOC-CACHE',
            'titel' => 'Cache test',
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            'auteur' => $this->organiser->name,
            'versie' => 1,
            'bestandsnaam' => 'file1.pdf',
            'inhoud' => '',
            'beschrijving' => '',
            'formaat' => 'application/pdf',
            'locked' => false,
            'bestandsgrootte' => 0,
            'creatiedatum' => now()->format('Y-m-d'),
            'wijzigingsdatum' => now()->toIso8601String(),
            'informatieobjecttype' => $documentTypeUrl,
            'indicatieGebruiksrecht' => false,
        ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
            'zaak' => $zgwZaakUrl,
            'informatieobject' => '',
        ], 201),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    Cache::put("zaak.{$zaak->id}.documenten", collect());
    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeTrue();

    $job = new UploadDocumentsJob(
        zaak: $zaak,
        files: [
            ['path' => 'documents/file1.pdf', 'titel' => 'Cache test', 'original_name' => 'file1.pdf', 'informatieobjecttype' => $documentTypeUrl],
        ],
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        userId: $this->organiser->id,
    );

    $job->handle();

    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeFalse();
});

test('job creates activity log entries per document and a multi-upload aggregate', function () {
    Storage::fake('local');
    Storage::put('documents/a.pdf', '%PDF-1.4 a');
    Storage::put('documents/b.pdf', '%PDF-1.4 b');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::sequence()
            ->push(['url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/log-a', 'uuid' => 'log-a', 'identificatie' => 'A', 'titel' => 'Doc A', 'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'auteur' => $this->organiser->name, 'versie' => 1, 'bestandsnaam' => 'a.pdf', 'inhoud' => '', 'beschrijving' => '', 'formaat' => 'application/pdf', 'locked' => false, 'bestandsgrootte' => 0, 'creatiedatum' => now()->format('Y-m-d'), 'wijzigingsdatum' => now()->toIso8601String(), 'informatieobjecttype' => $documentTypeUrl, 'indicatieGebruiksrecht' => false], 201)
            ->push(['url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/log-b', 'uuid' => 'log-b', 'identificatie' => 'B', 'titel' => 'Doc B', 'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'auteur' => $this->organiser->name, 'versie' => 1, 'bestandsnaam' => 'b.pdf', 'inhoud' => '', 'beschrijving' => '', 'formaat' => 'application/pdf', 'locked' => false, 'bestandsgrootte' => 0, 'creatiedatum' => now()->format('Y-m-d'), 'wijzigingsdatum' => now()->toIso8601String(), 'informatieobjecttype' => $documentTypeUrl, 'indicatieGebruiksrecht' => false], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(['url' => '', 'zaak' => $zgwZaakUrl, 'informatieobject' => ''], 201),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $job = new UploadDocumentsJob(
        zaak: $zaak,
        files: [
            ['path' => 'documents/a.pdf', 'titel' => 'Doc A', 'original_name' => 'a.pdf', 'informatieobjecttype' => $documentTypeUrl],
            ['path' => 'documents/b.pdf', 'titel' => 'Doc B', 'original_name' => 'b.pdf', 'informatieobjecttype' => $documentTypeUrl],
        ],
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        userId: $this->organiser->id,
    );

    $job->handle();

    $createdActivities = Activity::where('log_name', 'document')->where('event', 'created')->get();
    expect($createdActivities)->toHaveCount(2);

    $multiUploadActivity = Activity::where('log_name', 'document')->where('event', 'multi_upload')->first();
    expect($multiUploadActivity)->not->toBeNull()
        ->and((int) $multiUploadActivity->properties->get('count'))->toBe(2);
});

test('job skips missing files and continues', function () {
    Storage::fake('local');
    Storage::put('documents/exists.pdf', '%PDF-1.4 content');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/skip-doc',
            'uuid' => 'skip-doc',
            'identificatie' => 'DOC-SKIP',
            'titel' => 'Bestaand',
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            'auteur' => $this->organiser->name,
            'versie' => 1,
            'bestandsnaam' => 'exists.pdf',
            'inhoud' => '',
            'beschrijving' => '',
            'formaat' => 'application/pdf',
            'locked' => false,
            'bestandsgrootte' => 0,
            'creatiedatum' => now()->format('Y-m-d'),
            'wijzigingsdatum' => now()->toIso8601String(),
            'informatieobjecttype' => $documentTypeUrl,
            'indicatieGebruiksrecht' => false,
        ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(['url' => '', 'zaak' => $zgwZaakUrl, 'informatieobject' => ''], 201),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $job = new UploadDocumentsJob(
        zaak: $zaak,
        files: [
            ['path' => 'documents/missing.pdf', 'titel' => 'Ontbreekt', 'original_name' => 'missing.pdf', 'informatieobjecttype' => $documentTypeUrl],
            ['path' => 'documents/exists.pdf', 'titel' => 'Bestaand', 'original_name' => 'exists.pdf', 'informatieobjecttype' => $documentTypeUrl],
        ],
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        userId: $this->organiser->id,
    );

    $job->handle();

    // Only 1 openzaak fetch + 1 upload + 1 link (the missing file is skipped)
    Http::assertSentCount(3);
});
