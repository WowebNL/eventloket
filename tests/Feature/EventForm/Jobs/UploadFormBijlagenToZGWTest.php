<?php

declare(strict_types=1);

/**
 * UploadFormBijlagenToZGW upload alle FileUpload-bijlagen die de
 * organisator tijdens het invullen heeft ge-upload als
 * zaakinformatieobject naar OpenZaak. Deze tests dekken de defensieve
 * paden — een echte round-trip met HTTP-fakes wordt impliciet gedekt
 * door SubmitEventFormTest's dispatch-assert.
 */

use App\Jobs\Submit\UploadFormBijlagenToZGW;
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

test('bijlage-pad dat niet meer op disk staat → log waarschuwing, geen call voor dat bestand', function () {
    Log::spy();

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/uuid-1',
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'form_state_snapshot' => ['values' => [
            'veiligheidsplan' => 'documents/verdwenen.pdf',
        ]],
    ]);

    // Disk is leeg → bestand bestaat niet.
    (new UploadFormBijlagenToZGW($zaak))->handle();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'bijlage ontbreekt op disk'))
        ->once();
    Http::assertNothingSent();
});
