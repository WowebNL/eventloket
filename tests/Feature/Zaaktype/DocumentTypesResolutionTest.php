<?php

declare(strict_types=1);

/**
 * Zaaktype::getDocumentTypes() resolves the informatieobjecttypen linked to a
 * zaaktype. The ZGW standard types the relation's `informatieobjecttype` field
 * as a string: OpenZaak returns a followable URL, while some backends (e.g. RX
 * Mission) return the omschrijving inline. These tests prove both shapes resolve
 * to a full InformatieObjectTypeData (with a real url), that the two paths do
 * not interfere, and that a single unresolvable type is skipped instead of
 * crashing the whole list.
 */

use App\Enums\Role;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Resolution is cached rememberForever; flush so cases do not leak.
    Cache::flush();
    Http::preventStrayRequests();
});

/**
 * A zaak whose document-type version snapshot points at $snapshotUrl, so
 * getDocumentTypes() resolves against a URL we control in the fakes.
 */
function zaakForDocumentTypes(string $snapshotUrl): Zaak
{
    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/latest',
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => null,
        'zgw_zaaktype_url' => $snapshotUrl,
    ]);
}

test('a URL informatieobjecttype is followed and the catalogus list is not touched', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $snapshotUrl = $base.'/zaaktypen/snap';
    $typeUrl = $base.'/informatieobjecttypen/1';

    $zaak = zaakForDocumentTypes($snapshotUrl);

    Http::fake([
        $base.'/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/zaaktype-informatieobjecttypen/1', 'zaaktype' => $snapshotUrl, 'catalogus' => $base.'/catalogussen/1', 'informatieobjecttype' => $typeUrl],
        ]), 200),
        $typeUrl => Http::response([
            'url' => $typeUrl, 'omschrijving' => 'Bijlage', 'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
        ], 200),
    ]);

    $types = $zaak->document_types;

    expect($types)->toHaveCount(1);
    expect((string) $types->first()->url)->toBe($typeUrl);
    expect($types->first()->omschrijving)->toBe('Bijlage');

    // The URL path must not fall back to a catalogus lookup.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'catalogus='));
});

test('an omschrijving informatieobjecttype is resolved via the catalogus list', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $snapshotUrl = $base.'/zaaktypen/snap';
    $catalogusUrl = $base.'/catalogussen/1';
    $typeUrl = $base.'/informatieobjecttypen/7';

    $zaak = zaakForDocumentTypes($snapshotUrl);

    Http::fake([
        $base.'/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/zaaktype-informatieobjecttypen/1', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => 'Beschikking op aanvraag'],
        ]), 200),
        $base.'/informatieobjecttypen?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/informatieobjecttypen/6', 'omschrijving' => 'Aanvullende informatie', 'vertrouwelijkheidaanduiding' => 'openbaar', 'catalogus' => $catalogusUrl],
            ['url' => $typeUrl, 'omschrijving' => 'Beschikking op aanvraag', 'vertrouwelijkheidaanduiding' => 'openbaar', 'catalogus' => $catalogusUrl],
        ]), 200),
    ]);

    $types = $zaak->document_types;

    expect($types)->toHaveCount(1);
    expect((string) $types->first()->url)->toBe($typeUrl);
    expect($types->first()->omschrijving)->toBe('Beschikking op aanvraag');
    expect($types->first()->vertrouwelijkheidaanduiding?->value)->toBe('openbaar');

    // The lookup was scoped to the relation's catalogus.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/informatieobjecttypen?')
        && str_contains($request->url(), 'catalogus='.urlencode($catalogusUrl)));
});

test('mixed URL and omschrijving relations resolve and the catalogus is listed once', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $snapshotUrl = $base.'/zaaktypen/snap';
    $catalogusUrl = $base.'/catalogussen/1';
    $urlType = $base.'/informatieobjecttypen/1';

    $zaak = zaakForDocumentTypes($snapshotUrl);

    Http::fake([
        $base.'/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/zaaktype-informatieobjecttypen/1', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => $urlType],
            ['url' => $base.'/zaaktype-informatieobjecttypen/2', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => 'Advies'],
            ['url' => $base.'/zaaktype-informatieobjecttypen/3', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => 'Factuur'],
        ]), 200),
        $urlType => Http::response([
            'url' => $urlType, 'omschrijving' => 'Bijlage', 'vertrouwelijkheidaanduiding' => 'openbaar',
        ], 200),
        $base.'/informatieobjecttypen?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/informatieobjecttypen/2', 'omschrijving' => 'Advies', 'vertrouwelijkheidaanduiding' => 'openbaar', 'catalogus' => $catalogusUrl],
            ['url' => $base.'/informatieobjecttypen/3', 'omschrijving' => 'Factuur', 'vertrouwelijkheidaanduiding' => 'openbaar', 'catalogus' => $catalogusUrl],
        ]), 200),
    ]);

    $types = $zaak->document_types;

    expect($types)->toHaveCount(3);
    expect($types->pluck('omschrijving')->sort()->values()->all())->toBe(['Advies', 'Bijlage', 'Factuur']);
    $types->each(fn ($type) => expect((string) $type->url)->toStartWith($base.'/informatieobjecttypen/'));

    // Two omschrijving relations share one catalogus: it must be listed once.
    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'catalogus=')))->toHaveCount(1);
});

test('an unresolvable omschrijving is skipped without throwing, keeping the rest', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $snapshotUrl = $base.'/zaaktypen/snap';
    $catalogusUrl = $base.'/catalogussen/1';

    Log::spy();

    $zaak = zaakForDocumentTypes($snapshotUrl);

    Http::fake([
        $base.'/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            // Resolvable.
            ['url' => $base.'/zaaktype-informatieobjecttypen/1', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => 'Advies'],
            // Omschrijving absent from the catalogus list.
            ['url' => $base.'/zaaktype-informatieobjecttypen/2', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => 'Bestaat niet'],
            // Omschrijving with no catalogus at all.
            ['url' => $base.'/zaaktype-informatieobjecttypen/3', 'zaaktype' => $snapshotUrl, 'informatieobjecttype' => 'Geen catalogus'],
        ]), 200),
        $base.'/informatieobjecttypen?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/informatieobjecttypen/2', 'omschrijving' => 'Advies', 'vertrouwelijkheidaanduiding' => 'openbaar', 'catalogus' => $catalogusUrl],
        ]), 200),
    ]);

    $types = $zaak->document_types;

    expect($types)->toHaveCount(1);
    expect($types->first()->omschrijving)->toBe('Advies');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'niet gevonden in catalogus'))
        ->once();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'zonder catalogus'))
        ->once();
});

test('a failing catalogus list is skipped without throwing', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $snapshotUrl = $base.'/zaaktypen/snap';
    $catalogusUrl = $base.'/catalogussen/1';

    Log::spy();

    $zaak = zaakForDocumentTypes($snapshotUrl);

    Http::fake([
        $base.'/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/zaaktype-informatieobjecttypen/1', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => 'Advies'],
        ]), 200),
        $base.'/informatieobjecttypen?*' => Http::response('boom', 500),
    ]);

    $types = $zaak->document_types;

    expect($types)->toHaveCount(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'van catalogus niet op te halen'))
        ->once();
});

test('the vertrouwelijkheid role filter still applies to omschrijving-resolved types', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $snapshotUrl = $base.'/zaaktypen/snap';
    $catalogusUrl = $base.'/catalogussen/1';

    // An organiser may only see zaakvertrouwelijk documents.
    $this->actingAs(User::factory()->create(['role' => Role::Organiser]));

    $zaak = zaakForDocumentTypes($snapshotUrl);

    Http::fake([
        $base.'/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/zaaktype-informatieobjecttypen/1', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => 'Zichtbaar'],
            ['url' => $base.'/zaaktype-informatieobjecttypen/2', 'zaaktype' => $snapshotUrl, 'catalogus' => $catalogusUrl, 'informatieobjecttype' => 'Verborgen'],
        ]), 200),
        $base.'/informatieobjecttypen?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/informatieobjecttypen/1', 'omschrijving' => 'Zichtbaar', 'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk', 'catalogus' => $catalogusUrl],
            ['url' => $base.'/informatieobjecttypen/2', 'omschrijving' => 'Verborgen', 'vertrouwelijkheidaanduiding' => 'vertrouwelijk', 'catalogus' => $catalogusUrl],
        ]), 200),
    ]);

    $types = $zaak->document_types;

    expect($types)->toHaveCount(1);
    expect($types->first()->omschrijving)->toBe('Zichtbaar');
});
