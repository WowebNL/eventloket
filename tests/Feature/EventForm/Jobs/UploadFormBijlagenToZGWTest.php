<?php

declare(strict_types=1);

/**
 * UploadFormBijlagenToZGW upload alle FileUpload-bijlagen die de
 * organisator tijdens het invullen heeft ge-upload als
 * zaakinformatieobject naar OpenZaak. Deze tests dekken de defensieve
 * paden — een echte round-trip met HTTP-fakes wordt impliciet gedekt
 * door SubmitEventFormTest's dispatch-assert.
 */

use App\Enums\Role;
use App\Exceptions\FormAttachmentUploadFailedException;
use App\Jobs\Submit\UploadFormBijlagenToZGW;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaakReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Woweb\Zgw\Data\Generated\Catalogi\InformatieObjectTypeData;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Notification::fake();
    Storage::fake('local');
    Http::preventStrayRequests();
});

test('zaak zonder ZGW-url → job logt waarschuwing en stopt', function () {
    Log::spy();

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => null,
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
    ]);

    (new UploadFormBijlagenToZGW($zaak))->handle();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'zaak heeft geen ZGW-url'))
        ->once();
    Http::assertNothingSent();
});

test('zaak zonder ingevulde bijlagen → geen HTTP-calls', function () {
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/uuid-1',
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'form_state_snapshot' => ['values' => ['watIsUwVoornaam' => 'Eva']],
    ]);

    (new UploadFormBijlagenToZGW($zaak))->handle();

    // Geen FileUpload-veld in de state → geen ZGW-call.
    Http::assertNothingSent();
});

test('happy path: bijlage op disk → 2 ZGW-POSTs + cache wordt geinvalideerd', function () {
    // Een organisator heeft tijdens het invullen een PDF ge-upload op de
    // bijlagen-stap; de Filament-FileUpload heeft 'm op de lokale disk
    // geparkeerd onder 'event-form-uploads/...'. Bij submit moet de job
    // dat bestand (1) als enkelvoudiginformatieobject naar OpenZaak
    // posten, (2) aan de zaak koppelen via zaakinformatieobjecten, en
    // (3) de gecachete documenten-lijst van de zaak invalideren zodat
    // 'ie meteen verschijnt op de Bestanden-tab.

    // Zaaktype::document_types filtert via auth()->user()->role; zonder
    // login crasht 'ie. In productie loopt deze job onder een organiser-
    // sessie, dus die rol simuleren we hier ook.
    $this->actingAs(User::factory()->create(['role' => Role::Organiser]));

    $zaaktype = Zaaktype::factory()->create();
    $org = Organisation::factory()->create(['name' => 'Media Tuin']);
    $pad = "event-form-uploads/{$org->uuid}/buurtfeest-veiligheidsplan.pdf";
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/abc-123',
        'organisation_id' => $org->id,
        'zaaktype_id' => $zaaktype->id,
        'form_state_snapshot' => ['values' => [
            'bijlagen1' => $pad,
        ]],
    ]);

    Storage::disk('local')->put($pad, 'fake pdf content');

    // Skip de OpenZaak-GETs door de twee caches te seeden die de job
    // achter de schermen consumeert: zaak.openzaak (voor bronorganisatie)
    // en zaaktype_x_document_types (voor het informatieobjecttype).
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
    // Document types are cached per (connection, zaaktype version url). The job reads
    // them via the zaak's version snapshot, which falls back to the OzZaak zaaktype.
    Cache::put('zaaktype_document_types_v2_'.md5('main|https://zgw.example.com/catalogi/api/v1/zaaktypen/zt-1'), collect([
        InformatieObjectTypeData::from([
            'uuid' => 'iot-1',
            'url' => 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/iot-1',
            'omschrijving' => 'Bijlage',
            'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
        ]),
    ]));

    // Pre-seed de documenten-cache zodat we straks kunnen bewijzen dat
    // clearZgwCache() 'm wegmaakt.
    Cache::put("zaak.{$zaak->id}.documenten", collect());

    Http::fake([
        '*/documenten/api/v1/enkelvoudiginformatieobjecten' => Http::response([
            'uuid' => 'doc-1',
            'url' => 'https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1',
            'creatiedatum' => '2026-05-06',
            'titel' => 'buurtfeest-veiligheidsplan.pdf',
            'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
            'auteur' => 'Media Tuin',
            'versie' => 1,
            'bestandsnaam' => 'buurtfeest-veiligheidsplan.pdf',
            'inhoud' => 'https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1/download',
            'beschrijving' => '',
            'informatieobjecttype' => 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/iot-1',
            'formaat' => 'application/pdf',
            'locked' => false,
        ], 201),
        '*/zaken/api/v1/zaakinformatieobjecten' => Http::response([
            'url' => 'https://zgw.example.com/zaken/api/v1/zaakinformatieobjecten/zio-1',
        ], 201),
    ]);

    (new UploadFormBijlagenToZGW($zaak))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['bestandsnaam'] === 'buurtfeest-veiligheidsplan.pdf'
        && $request->data()['titel'] === 'buurtfeest-veiligheidsplan.pdf'
        && $request->data()['inhoud'] === base64_encode('fake pdf content')
        && $request->data()['informatieobjecttype'] === 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/iot-1'
        && $request->data()['bronorganisatie'] === '820151130'
        // Every document we push is sent with the finalised 'definitief' status.
        && $request->data()['status'] === 'definitief');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaakinformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['zaak'] === $zaak->zgw_zaak_url
        && $request->data()['informatieobject'] === 'https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1');

    // De cache-bust is essentieel: zonder dit blijft Zaak::documenten
    // de oude (lege) lijst teruggeven en ziet de behandelaar de net-
    // geuploade bijlage niet bij de zaak.
    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeFalse();
});

test('bijlage-pad dat niet meer op disk staat → log waarschuwing, geen call voor dat bestand', function () {
    Log::spy();

    $org = Organisation::factory()->create();
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/uuid-1',
        'organisation_id' => $org->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'form_state_snapshot' => ['values' => [
            'veiligheidsplan' => "event-form-uploads/{$org->uuid}/verdwenen.pdf",
        ]],
    ]);

    // Disk is leeg → bestand bestaat niet.
    (new UploadFormBijlagenToZGW($zaak))->handle();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'bijlage ontbreekt op disk'))
        ->once();
    Http::assertNothingSent();
});

test('security: bijlage-pad buiten de eigen organisatie-map wordt geweigerd', function () {
    Log::spy();

    // Aanvaller (organisatie A) injecteert via de form-state een pad dat
    // naar de upload-map van een ándere organisatie (id 999) wijst. Het
    // bestand bestáát op disk, maar valt buiten event-form-uploads/<eigen
    // org_id>/ → de job mag het niet uploaden.
    $org = Organisation::factory()->create();
    $vreemdPad = 'event-form-uploads/999/geheim-veiligheidsplan.pdf';
    Storage::disk('local')->put($vreemdPad, 'andermans vertrouwelijke plan');

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/uuid-1',
        'organisation_id' => $org->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'form_state_snapshot' => ['values' => [
            'bijlagen1' => $vreemdPad,
        ]],
    ]);

    (new UploadFormBijlagenToZGW($zaak))->handle();

    Log::shouldHaveReceived('critical')
        ->withArgs(fn (string $message) => str_contains($message, 'buiten de eigen upload-map geweigerd'))
        ->once();
    Http::assertNothingSent();
});

test('security: path traversal via ../ in pad wordt geweigerd', function () {
    Log::spy();

    $orgA = Organisation::factory()->create();
    $orgB = Organisation::factory()->create();

    // Het doelbestand van organisatie B bestaat op disk — aanvaller kent het pad.
    $doelPad = "event-form-uploads/{$orgB->uuid}/geheim.pdf";
    Storage::disk('local')->put($doelPad, 'geheim document');

    // Traversal-pad dat via ../ de map van org B bereikt, maar str_starts_with
    // op de prefix van org A passeert als er geen normalisatie plaatsvindt.
    $traversalPad = "event-form-uploads/{$orgA->uuid}/../{$orgB->uuid}/geheim.pdf";

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/uuid-1',
        'organisation_id' => $orgA->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'form_state_snapshot' => ['values' => [
            'bijlagen1' => $traversalPad,
        ]],
    ]);

    (new UploadFormBijlagenToZGW($zaak))->handle();

    Log::shouldHaveReceived('critical')
        ->withArgs(fn (string $message) => str_contains($message, 'buiten de eigen upload-map geweigerd'))
        ->once();
    Http::assertNothingSent();
});

test('security: bijlage met niet-toegestaan bestandstype wordt geweigerd', function () {
    Log::spy();

    // Een binnen de eigen map opgeslagen, maar uitvoerbaar bestand mag niet
    // alsnog als zaakdocument naar OpenZaak — de submit-flow draait geen
    // FileUpload-validatie, dus de job is de laatste verdedigingslinie.
    $org = Organisation::factory()->create();
    $pad = "event-form-uploads/{$org->uuid}/kwaadaardig.php";
    Storage::disk('local')->put($pad, "<?php system(\$_GET['c']); ?>");

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/uuid-1',
        'organisation_id' => $org->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'form_state_snapshot' => ['values' => [
            'bijlagen1' => $pad,
        ]],
    ]);

    (new UploadFormBijlagenToZGW($zaak))->handle();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'niet-toegestaan bestandstype geweigerd'))
        ->once();
    Http::assertNothingSent();
});

/**
 * Creates a zaak with the given attachments (file name => content) on disk in
 * one multiple FileUpload field, and seeds the caches the job reads, so only
 * the upload requests go over HTTP.
 *
 * @param  array<string, string>  $files
 */
function zaakWithAttachments(array $files): Zaak
{
    test()->actingAs(User::factory()->create(['role' => Role::Organiser]));

    $org = Organisation::factory()->create();
    $paths = [];
    foreach ($files as $name => $content) {
        $paths[$name] = "event-form-uploads/{$org->uuid}/{$name}";
        Storage::disk('local')->put($paths[$name], $content);
    }

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/abc-123',
        'organisation_id' => $org->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'form_state_snapshot' => ['values' => [
            'bijlagen1' => array_values($paths),
        ]],
    ]);

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
            'omschrijving' => 'Bijlage',
            'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
        ]),
    ]));

    return $zaak;
}

function documentUrlFor(string $name): string
{
    return 'https://zgw.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/doc-'.md5($name);
}

/**
 * Fakes the Documenten and Zaken API.
 *
 * - The document POST answers per file name: a name listed in
 *   $documentFailures gets that HTTP status, any other file is created at
 *   documentUrlFor($name). The uuid in the response body differs from the one
 *   in the url on purpose, to prove the cleanup only trusts the url.
 * - The link POST answers with $linkStatus.
 * - The link lookup answers per $linkLookup: 'exists' returns the link of
 *   this document, 'other' returns only a link of another document (a backend
 *   that ignores the filter), 'none' returns no links and 'error' a 500.
 *
 * Pass an ArrayObject to change the failures between two runs of the job:
 * Http::fake() stacks, so a second call would not replace the first.
 *
 * @param  array<string, int>|ArrayObject<string, int>  $documentFailures  file name => HTTP status
 */
function fakeZgwUploads(array|ArrayObject $documentFailures = [], int $linkStatus = 201, string $linkLookup = 'none'): void
{
    Http::fake(function (Request $request) use ($documentFailures, $linkStatus, $linkLookup) {
        $url = $request->url();
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($request->method() === 'POST' && str_ends_with($path, '/documenten/api/v1/enkelvoudiginformatieobjecten')) {
            $name = $request->data()['bestandsnaam'];
            if (isset($documentFailures[$name])) {
                $status = $documentFailures[$name];

                return $status === 400
                    ? Http::response([
                        'code' => 'invalid',
                        'title' => 'Invalid input.',
                        'invalidParams' => [['name' => 'informatieobjecttype', 'code' => 'invalid', 'reason' => 'Niet toegestaan.']],
                    ], 400)
                    : Http::response(['code' => 'error'], $status);
            }

            return Http::response([
                'uuid' => 'body-'.md5($name),
                'url' => documentUrlFor($name),
                'creatiedatum' => '2026-05-06',
                'titel' => $name,
                'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
                'auteur' => 'Organisator',
                'versie' => 1,
                'bestandsnaam' => $name,
                'inhoud' => documentUrlFor($name).'/download',
                'beschrijving' => '',
                'informatieobjecttype' => 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/iot-1',
                'formaat' => 'application/pdf',
                'locked' => false,
            ], 201);
        }

        if ($request->method() === 'DELETE' && str_contains($path, '/documenten/api/v1/enkelvoudiginformatieobjecten/')) {
            return Http::response(null, 204);
        }

        if (str_ends_with($path, '/zaken/api/v1/zaakinformatieobjecten')) {
            if ($request->method() === 'GET') {
                if ($linkLookup === 'error') {
                    return Http::response(['code' => 'error'], 500);
                }
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $links = match ($linkLookup) {
                    'exists' => [[
                        'url' => 'https://zgw.example.com/zaken/api/v1/zaakinformatieobjecten/zio-1',
                        'zaak' => $query['zaak'] ?? '',
                        'informatieobject' => $query['informatieobject'] ?? '',
                    ]],
                    'other' => [[
                        'url' => 'https://zgw.example.com/zaken/api/v1/zaakinformatieobjecten/zio-2',
                        'zaak' => $query['zaak'] ?? '',
                        'informatieobject' => documentUrlFor('iets-anders.pdf'),
                    ]],
                    default => [],
                };

                return Http::response($links, 200);
            }

            return $linkStatus === 201
                ? Http::response(['url' => 'https://zgw.example.com/zaken/api/v1/zaakinformatieobjecten/zio-1'], 201)
                : Http::response(['code' => 'error'], $linkStatus);
        }

        return Http::response(null, 404);
    });
}

function documentPostsFor(string $name): int
{
    return Http::recorded(fn (Request $request) => $request->method() === 'POST'
        && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/enkelvoudiginformatieobjecten')
        && $request->data()['bestandsnaam'] === $name)->count();
}

function documentDeletes(): int
{
    return Http::recorded(fn (Request $request) => $request->method() === 'DELETE')->count();
}

function uploadedFileNames(Zaak $zaak): array
{
    return Activity::query()
        ->where('log_name', 'document')
        ->where('subject_id', $zaak->id)
        ->pluck('properties')
        ->map(fn ($properties) => data_get($properties, 'filename'))
        ->sort()
        ->values()
        ->all();
}

test('a file the API refuses with a 400 does not stop the other files, and fails the job without retry', function () {
    Exceptions::fake();
    $zaak = zaakWithAttachments([
        'a-plan.pdf' => 'a',
        'b-geweigerd.pdf' => 'b',
        'c-kaart.pdf' => 'c',
    ]);
    fakeZgwUploads(['b-geweigerd.pdf' => 400]);

    $job = (new UploadFormBijlagenToZGW($zaak))->withFakeQueueInteractions();
    $job->handle();

    expect(documentPostsFor('a-plan.pdf'))->toBe(1)
        ->and(documentPostsFor('b-geweigerd.pdf'))->toBe(1)
        ->and(documentPostsFor('c-kaart.pdf'))->toBe(1)
        ->and(uploadedFileNames($zaak))->toBe(['a-plan.pdf', 'c-kaart.pdf']);

    // Failed for good, so it lands in the failed jobs for a manual retry.
    $job->assertFailed();

    Exceptions::assertReported(fn (FormAttachmentUploadFailedException $e) => $e->status === 400
        && $e->step === FormAttachmentUploadFailedException::STEP_DOCUMENT
        && $e->invalidParams === [['name' => 'informatieobjecttype', 'code' => 'invalid']]);
});

test('a retry skips the files an earlier attempt already uploaded', function () {
    Exceptions::fake();
    $zaak = zaakWithAttachments([
        'a-plan.pdf' => 'a',
        'b-tijdelijk.pdf' => 'b',
    ]);

    // First attempt: the API is briefly unavailable for one file.
    $failures = new ArrayObject(['b-tijdelijk.pdf' => 503]);
    fakeZgwUploads($failures);

    expect(fn () => (new UploadFormBijlagenToZGW($zaak))->handle())
        ->toThrow(RuntimeException::class, 'failed with a temporary error');
    expect(uploadedFileNames($zaak))->toBe(['a-plan.pdf']);

    // Second attempt: the API is back. Only the missing file is sent again.
    $failures->exchangeArray([]);

    $job = (new UploadFormBijlagenToZGW($zaak))->withFakeQueueInteractions();
    $job->handle();

    expect(documentPostsFor('a-plan.pdf'))->toBe(1)
        ->and(documentPostsFor('b-tijdelijk.pdf'))->toBe(2)
        ->and(uploadedFileNames($zaak))->toBe(['a-plan.pdf', 'b-tijdelijk.pdf']);
    $job->assertNotFailed();
});

test('a 429 or 408 from the API is temporary, so the job is retried', function (int $status) {
    Exceptions::fake();
    $zaak = zaakWithAttachments(['a-plan.pdf' => 'a']);
    fakeZgwUploads(['a-plan.pdf' => $status]);

    $job = (new UploadFormBijlagenToZGW($zaak))->withFakeQueueInteractions();

    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'failed with a temporary error');
    $job->assertNotFailed();
})->with([429, 408]);

test('a refused link removes the document again, by the uuid from its url', function () {
    Exceptions::fake();
    $zaak = zaakWithAttachments(['a-plan.pdf' => 'a']);
    fakeZgwUploads(linkStatus: 400);

    $job = (new UploadFormBijlagenToZGW($zaak))->withFakeQueueInteractions();
    $job->handle();

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === documentUrlFor('a-plan.pdf'));
    // A refused link was certainly not created, so there is nothing to look up.
    Http::assertNotSent(fn (Request $request) => $request->method() === 'GET');
    Exceptions::assertReported(fn (FormAttachmentUploadFailedException $e) => $e->step === FormAttachmentUploadFailedException::STEP_LINK);
    expect(uploadedFileNames($zaak))->toBe([]);
    $job->assertFailed();
});

test('a link that timed out but was created anyway counts as uploaded', function () {
    Exceptions::fake();
    $zaak = zaakWithAttachments(['a-plan.pdf' => 'a']);
    fakeZgwUploads(linkStatus: 504, linkLookup: 'exists');

    $job = (new UploadFormBijlagenToZGW($zaak))->withFakeQueueInteractions();
    $job->handle();

    expect(documentDeletes())->toBe(0)
        ->and(uploadedFileNames($zaak))->toBe(['a-plan.pdf']);
    $job->assertNotFailed();
});

test('a link that timed out and was not created removes the document and retries', function () {
    Exceptions::fake();
    $zaak = zaakWithAttachments(['a-plan.pdf' => 'a']);
    fakeZgwUploads(linkStatus: 504, linkLookup: 'none');

    expect(fn () => (new UploadFormBijlagenToZGW($zaak))->handle())
        ->toThrow(RuntimeException::class, 'failed with a temporary error');

    expect(documentDeletes())->toBe(1)
        ->and(uploadedFileNames($zaak))->toBe([]);
});

test('a link lookup that only returns another document counts as not linked', function () {
    Exceptions::fake();
    $zaak = zaakWithAttachments(['a-plan.pdf' => 'a']);
    // A backend that ignores the informatieobject filter returns the links of
    // other documents; those must not count as the link of this one.
    fakeZgwUploads(linkStatus: 504, linkLookup: 'other');

    expect(fn () => (new UploadFormBijlagenToZGW($zaak))->handle())
        ->toThrow(RuntimeException::class, 'failed with a temporary error');

    expect(documentDeletes())->toBe(1)
        ->and(uploadedFileNames($zaak))->toBe([]);
});

test('a link lookup that fails keeps the document in place', function () {
    Exceptions::fake();
    $zaak = zaakWithAttachments(['a-plan.pdf' => 'a']);
    // Whether the link exists is unknown, so removing the document could
    // break a link the case already has.
    fakeZgwUploads(linkStatus: 504, linkLookup: 'error');

    expect(fn () => (new UploadFormBijlagenToZGW($zaak))->handle())
        ->toThrow(RuntimeException::class, 'failed with a temporary error');

    expect(documentDeletes())->toBe(0)
        ->and(uploadedFileNames($zaak))->toBe([]);
});
