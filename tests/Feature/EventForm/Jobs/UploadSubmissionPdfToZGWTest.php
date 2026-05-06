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

use App\Jobs\Submit\UploadSubmissionPdfToZGW;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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
