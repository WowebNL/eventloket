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
use App\Jobs\Submit\UploadFormBijlagenToZGW;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\InformatieobjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
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
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/abc-123',
        'organisation_id' => Organisation::factory()->create(['name' => 'Media Tuin'])->id,
        'zaaktype_id' => $zaaktype->id,
        'form_state_snapshot' => ['values' => [
            'bijlagen1' => 'event-form-uploads/buurtfeest-veiligheidsplan.pdf',
        ]],
    ]);

    Storage::disk('local')->put(
        'event-form-uploads/buurtfeest-veiligheidsplan.pdf',
        'fake pdf content'
    );

    // Skip de OpenZaak-GETs door de twee caches te seeden die de job
    // achter de schermen consumeert: zaak.openzaak (voor bronorganisatie)
    // en zaaktype_x_document_types (voor het informatieobjecttype).
    Cache::put("zaak.{$zaak->id}.openzaak", new OzZaak(
        uuid: 'abc-123',
        url: $zaak->zgw_zaak_url,
        identificatie: $zaak->public_id,
        zaaktype: 'https://zgw.example.com/catalogi/api/v1/zaaktypen/zt-1',
        omschrijving: 'Test',
        startdatum: '2026-05-01',
        registratiedatum: '2026-05-01',
        einddatum: null,
        einddatumGepland: null,
        uiterlijkeEinddatumAfdoening: null,
        bronorganisatie: '820151130',
        zaakgeometrie: null,
    ));
    Cache::put("zaaktype_{$zaaktype->id}_document_types", collect([
        new InformatieobjectType(
            uuid: 'iot-1',
            url: 'https://zgw.example.com/catalogi/api/v1/informatieobjecttypen/iot-1',
            omschrijving: 'Bijlage',
            vertrouwelijkheidaanduiding: 'zaakvertrouwelijk',
        ),
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
        && $request->data()['bronorganisatie'] === '820151130');

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
