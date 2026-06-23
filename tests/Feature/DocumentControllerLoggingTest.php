<?php

declare(strict_types=1);

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Http::fake([
        ZgwHttpFake::$baseUrl.'*' => Http::response('pdf-content', 200),
    ]);
});

function makeTestDocument(string $documentUuid = 'doc-uuid-1'): Informatieobject
{
    return new Informatieobject(
        uuid: $documentUuid,
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$documentUuid,
        creatiedatum: now()->toIso8601String(),
        titel: 'Test Document',
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        auteur: 'Test',
        versie: 1,
        bestandsnaam: 'test.pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$documentUuid.'/download',
        beschrijving: 'Test beschrijving',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );
}

/**
 * @return array{zaak: Zaak, municipality: Municipality}
 */
function makeZaakWithDocument(string $documentUuid = 'doc-uuid-1'): array
{
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
    $zaak = Zaak::factory()->create(['zaaktype_id' => $zaaktype->id]);
    Cache::forever("zaak.{$zaak->id}.documenten", collect([makeTestDocument($documentUuid)]));

    return ['zaak' => $zaak, 'municipality' => $municipality];
}

test('viewing a document is logged with view event', function () {
    ['zaak' => $zaak, 'municipality' => $municipality] = makeZaakWithDocument();
    $user = User::factory()->create(['role' => Role::Reviewer]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user)
        ->get(route('zaak.documents.view', [$zaak, 'doc-uuid-1', 'view']));

    $activity = Activity::where('log_name', 'document')->where('event', 'view')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Bekeken')
        ->and($activity->causer_id)->toEqual($user->id)
        ->and($activity->subject_id)->toEqual($zaak->id)
        ->and($activity->properties->get('document_uuid'))->toBe('doc-uuid-1')
        ->and($activity->properties->get('filename'))->toBe('test.pdf');
});

test('downloading a document is logged with download event', function () {
    ['zaak' => $zaak, 'municipality' => $municipality] = makeZaakWithDocument();
    $user = User::factory()->create(['role' => Role::Reviewer]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user)
        ->get(route('zaak.documents.view', [$zaak, 'doc-uuid-1', 'download']));

    $activity = Activity::where('log_name', 'document')->where('event', 'download')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Gedownload')
        ->and($activity->causer_id)->toEqual($user->id)
        ->and($activity->subject_id)->toEqual($zaak->id)
        ->and($activity->properties->get('document_uuid'))->toBe('doc-uuid-1')
        ->and($activity->properties->get('filename'))->toBe('test.pdf');
});

test('document access without type parameter is logged as view', function () {
    ['zaak' => $zaak, 'municipality' => $municipality] = makeZaakWithDocument();
    $user = User::factory()->create(['role' => Role::Reviewer]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user)
        ->get(route('zaak.documents.view', [$zaak, 'doc-uuid-1']));

    $activity = Activity::where('log_name', 'document')->where('event', 'view')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('document_uuid'))->toBe('doc-uuid-1');
});

test('unauthenticated document access is not logged', function () {
    ['zaak' => $zaak] = makeZaakWithDocument();

    $this->get(route('zaak.documents.view', [$zaak, 'doc-uuid-1', 'view']));

    expect(Activity::where('log_name', 'document')->first())->toBeNull();
});

// Security: Content-Type header validation (A03)

test('allowed mime type is passed through as Content-Type', function () {
    ['zaak' => $zaak, 'municipality' => $municipality] = makeZaakWithDocument();
    $user = User::factory()->create(['role' => Role::Reviewer]);
    $user->municipalities()->attach($municipality);

    $response = $this->actingAs($user)
        ->get(route('zaak.documents.view', [$zaak, 'doc-uuid-1', 'view']));

    expect($response->headers->get('Content-Type'))->toStartWith('application/pdf');
});

test('disallowed mime type is replaced with application/octet-stream', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
    $zaak = Zaak::factory()->create(['zaaktype_id' => $zaaktype->id]);

    $document = new Informatieobject(
        uuid: 'doc-html',
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-html',
        creatiedatum: now()->toIso8601String(),
        titel: 'Malicious Document',
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        auteur: 'Attacker',
        versie: 1,
        bestandsnaam: 'evil.html',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-html/download',
        beschrijving: 'XSS payload',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'text/html',
        locked: false,
    );

    Cache::forever("zaak.{$zaak->id}.documenten", collect([$document]));

    $user = User::factory()->create(['role' => Role::Reviewer]);
    $user->municipalities()->attach($municipality);

    $response = $this->actingAs($user)
        ->get(route('zaak.documents.view', [$zaak, 'doc-html', 'view']));

    expect($response->headers->get('Content-Type'))->toStartWith('application/octet-stream');
});

test('javascript mime type is replaced with application/octet-stream', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
    $zaak = Zaak::factory()->create(['zaaktype_id' => $zaaktype->id]);

    $document = new Informatieobject(
        uuid: 'doc-js',
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-js',
        creatiedatum: now()->toIso8601String(),
        titel: 'JS Document',
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        auteur: 'Attacker',
        versie: 1,
        bestandsnaam: 'script.js',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-js/download',
        beschrijving: 'JS payload',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/javascript',
        locked: false,
    );

    Cache::forever("zaak.{$zaak->id}.documenten", collect([$document]));

    $user = User::factory()->create(['role' => Role::Reviewer]);
    $user->municipalities()->attach($municipality);

    $response = $this->actingAs($user)
        ->get(route('zaak.documents.view', [$zaak, 'doc-js', 'view']));

    expect($response->headers->get('Content-Type'))->toStartWith('application/octet-stream');
});
