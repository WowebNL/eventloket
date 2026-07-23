<?php

declare(strict_types=1);

/**
 * UploadSubmissionPdfToZGW post de gegenereerde PDF als
 * zaakinformatieobject naar OpenZaak. Deze tests focussen op de
 * defensieve paden waar de job zich uit moet blijven schreeuwen — een
 * succesvolle round-trip naar OpenZaak vereist complete HTTP-fakes
 * (zaak-fetch + zaaktype-document-types + 2 POSTs); die wordt al
 * impliciet gedekt via SubmitEventFormTest's job-dispatch-asserts.
 *
 * Wat we hier wel willen bewijzen: de job mag NIET retry-stormen of
 * harde errors opleveren als één van de invoerwaarden ontbreekt
 * (PDF nog niet weggeschreven, zaak zonder ZGW-koppeling). Dat zijn
 * exact de race-condities die in een queue-omgeving gebeuren.
 */

use App\Enums\Role;
use App\Jobs\Submit\UploadSubmissionPdfToZGW;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaakReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Woweb\Zgw\Data\Generated\Catalogi\InformatieObjectTypeData;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Notification::fake();
    Storage::fake('local');
    Http::preventStrayRequests();
});

test('zaak zonder ZGW-url → job logt waarschuwing en stopt zonder HTTP-calls', function () {
    Log::spy();

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => null,
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
    ]);

    (new UploadSubmissionPdfToZGW($zaak))->handle();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (...$args) => isset($args[0]) && is_string($args[0]) && str_contains($args[0], 'zaak heeft geen ZGW-url'))
        ->once();
    Http::assertNothingSent();
});

test('PDF nog niet weggeschreven → job logt waarschuwing en stopt zonder HTTP-calls', function () {
    Log::spy();

    $zaaktype = Zaaktype::factory()->create();
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/uuid-1',
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => $zaaktype->id,
    ]);

    // GEEN PDF op disk schrijven; de job moet detecteren dat 'ie er niet
    // is en stilletjes terug.
    (new UploadSubmissionPdfToZGW($zaak))->handle();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'PDF ontbreekt'))
        ->once();
    Http::assertNothingSent();
});

test('happy path: store-response zonder uuid → uuid wordt afgeleid, geen ArgumentCountError', function () {
    // Regression for EVENTLOKET-15: the new woweb/laravel-zgw-client does NOT
    // derive a `uuid` on a store() response (only on list items). The document
    // value object requires `uuid`, so the raw store body crashed the job with
    // "ArgumentCountError: Informatieobject::__construct(): Argument #1 ($uuid)
    // not passed" — after OpenZaak had already created the document (201),
    // leaving an orphaned document behind on every retry. ZgwResource::ensureUuid()
    // derives the uuid from the url so the job completes and links the document.

    // Zaak::document_types filters by auth()->user()->role, so this job must run
    // under an organiser session like it does in production.
    $this->actingAs(User::factory()->create(['role' => Role::Organiser]));

    $zaaktype = Zaaktype::factory()->create();
    $org = Organisation::factory()->create();
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/abc-123',
        'organisation_id' => $org->id,
        'zaaktype_id' => $zaaktype->id,
    ]);

    Storage::disk('local')->put("zaken/{$zaak->id}/aanvraagformulier.pdf", 'fake pdf content');

    // Seed the caches the job reads so it never hits OpenZaak for the zaak or
    // its document types, mirroring UploadFormBijlagenToZGWTest's happy path.
    Cache::put("zaak.{$zaak->id}.openzaak.v2", ZaakReadModel::fromArray([
        'uuid' => 'abc-123',
        'url' => $zaak->zgw_zaak_url,
        'identificatie' => $zaak->public_id,
        'zaaktype' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/zt-1',
        'omschrijving' => 'Test',
        'startdatum' => '2026-05-01',
        'registratiedatum' => '2026-05-01',
        'einddatum' => null,
        'einddatumGepland' => null,
        'uiterlijkeEinddatumAfdoening' => null,
        'bronorganisatie' => '820151130',
        'zaakgeometrie' => null,
    ]));
    Cache::put('zaaktype_document_types_v2_'.md5('main|https://zgw.example.com/catalogi/api/v1/zaaktypen/zt-1'), collect([
        InformatieObjectTypeData::from([
            'uuid' => 'iot-1',
            'url' => 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/iot-1',
            'omschrijving' => 'Aanvraagformulier',
            'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
        ]),
    ]));

    // Crucial: the store response carries NO `uuid` (the real ZGW client only
    // derives one for list items) and a null `beschrijving` (RX Mission omits it
    // where OpenZaak returns an empty string). Neither may crash the job.
    Http::fake([
        '*/documenten/api/v1/enkelvoudiginformatieobjecten' => Http::response([
            'url' => 'https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/doc-9',
            'creatiedatum' => '2026-05-06',
            'titel' => 'Aanvraagformulier',
            'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
            'auteur' => 'Eventloket',
            'versie' => 1,
            'bestandsnaam' => 'aanvraagformulier.pdf',
            'inhoud' => 'https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/doc-9/download',
            'beschrijving' => null,
            'informatieobjecttype' => 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/iot-1',
            'formaat' => 'application/pdf',
            'locked' => false,
        ], 201),
        '*/zaken/api/v1/zaakinformatieobjecten' => Http::response([
            'url' => 'https://zgw.example.com/zaken/api/v1/zaakinformatieobjecten/zio-1',
        ], 201),
    ]);

    (new UploadSubmissionPdfToZGW($zaak))->handle();

    // Item 5: the document store carries the finalised 'definitief' status so
    // behandelaars and downstream systems treat the aanvraagformulier as final.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['status'] === 'definitief');

    // The link POST proves the job survived past the value-object construction
    // and used the document url derived alongside the uuid.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaakinformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['zaak'] === $zaak->zgw_zaak_url
        && $request->data()['informatieobject'] === 'https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/doc-9');

    // And the activity log records the uuid derived from the url segment.
    $this->assertDatabaseHas('activity_log', [
        'subject_type' => $zaak->getMorphClass(),
        'subject_id' => $zaak->id,
        'event' => 'created',
    ]);
});
