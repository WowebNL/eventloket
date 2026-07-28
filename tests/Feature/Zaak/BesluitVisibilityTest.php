<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

/**
 * @param  array<string, mixed>  $besluitOverrides
 */
function fakeBesluit(string $verzenddatum, ?string $documentStatus, array $besluitOverrides = [], bool $isOneGround = false): Zaak
{
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $besluitUrl = ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1';
    $besluittypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/besluittypen/1';

    // A null document status means the besluit has no besluitinformatieobjecten
    // at all, which is how a besluit taken in the ZGW backend itself commonly
    // looks: the decision document is kept as a zaakdocument instead.
    $besluitInformatieObjecten = [];
    if ($documentStatus !== null) {
        $docUrl = ZgwHttpFake::fakeSingleDocument('1', ['status' => $documentStatus]);
        $besluitInformatieObjecten = [[
            'url' => ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten/1',
            'besluit' => $besluitUrl,
            'informatieobject' => $docUrl,
        ]];
    }

    Http::fake([
        ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten?*' => Http::response(ZgwHttpFake::envelope([array_merge([
            'url' => $besluitUrl,
            'identificatie' => 'BES-1',
            'besluittype' => $besluittypeUrl,
            'zaak' => $zaakUrl,
            'datum' => '2026-01-01',
            'toelichting' => 'Test besluit',
            'ingangsdatum' => '2026-01-01',
            'verzenddatum' => $verzenddatum,
            'vervaldatum' => null,
        ], $besluitOverrides)]), 200),
        ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten?*' => Http::response(ZgwHttpFake::envelope($besluitInformatieObjecten), 200),
        $besluittypeUrl => Http::response([
            'url' => $besluittypeUrl,
            'omschrijving' => 'Vergunning',
        ], 200),
    ]);

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => $zaaktype->id,
    ]);

    if ($isOneGround) {
        config()->set("zgw.connections.{$zaak->zgwConnectionName()}.is_oneground", true);
    }

    return $zaak;
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));
});

test('a definitief besluit with a reached verzenddatum is shown', function () {
    $zaak = fakeBesluit(verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(), documentStatus: 'definitief');

    expect($zaak->besluiten)->toHaveCount(1);
});

test('a besluit with a future verzenddatum is hidden', function () {
    $zaak = fakeBesluit(verzenddatum: now('Europe/Amsterdam')->addDays(3)->toDateString(), documentStatus: 'definitief');

    expect($zaak->besluiten)->toHaveCount(0);
});

test('a besluit with only a concept document is hidden', function () {
    $zaak = fakeBesluit(verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(), documentStatus: 'in_bewerking');

    expect($zaak->besluiten)->toHaveCount(0);
});

test('a besluit with an archived document is shown', function () {
    // A backend that archives on the final status (OneGround does so
    // immediately) sets every document to gearchiveerd. That freezes the
    // document, it does not withdraw it, so the besluit stays visible.
    $zaak = fakeBesluit(verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(), documentStatus: 'gearchiveerd');

    expect($zaak->besluiten)->toHaveCount(1);
});

test('on a OneGround connection a besluit without besluitdocumenten is shown', function () {
    $zaak = fakeBesluit(
        verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(),
        documentStatus: null,
        isOneGround: true,
    );

    expect($zaak->besluiten)->toHaveCount(1);
});

test('on a standard connection a besluit without besluitdocumenten stays hidden', function () {
    $zaak = fakeBesluit(
        verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(),
        documentStatus: null,
    );

    expect($zaak->besluiten)->toHaveCount(0);
});

test('a besluit without verzenddatum falls back to its publicatiedatum', function () {
    // verzenddatum is optional in the ZGW Besluiten API; without a fallback such
    // a besluit would never become visible.
    $zaak = fakeBesluit(
        verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(),
        documentStatus: 'definitief',
        besluitOverrides: [
            'verzenddatum' => null,
            'publicatiedatum' => now('Europe/Amsterdam')->subDay()->toDateString(),
        ],
    );

    expect($zaak->besluiten)->toHaveCount(1);
});

test('a besluit without any send or publication date is hidden', function () {
    $zaak = fakeBesluit(
        verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(),
        documentStatus: 'definitief',
        besluitOverrides: ['verzenddatum' => null],
    );

    expect($zaak->besluiten)->toHaveCount(0);
});

test('besluitdocumenten are filtered to what the role may see', function () {
    // The filter used to build a new value object and throw it away, so nothing
    // was actually filtered.
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $besluitUrl = ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1';
    $besluittypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/besluittypen/1';
    $zichtbaar = ZgwHttpFake::fakeSingleDocument('1', ['status' => 'definitief', 'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk']);
    $verborgen = ZgwHttpFake::fakeSingleDocument('2', ['status' => 'definitief', 'vertrouwelijkheidaanduiding' => 'geheim']);

    Http::fake([
        ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten?*' => Http::response(ZgwHttpFake::envelope([[
            'url' => $besluitUrl,
            'identificatie' => 'BES-1',
            'besluittype' => $besluittypeUrl,
            'zaak' => $zaakUrl,
            'datum' => '2026-01-01',
            'toelichting' => 'Test besluit',
            'ingangsdatum' => '2026-01-01',
            'verzenddatum' => now('Europe/Amsterdam')->subDay()->toDateString(),
            'vervaldatum' => null,
        ]]), 200),
        ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten/1', 'besluit' => $besluitUrl, 'informatieobject' => $zichtbaar],
            ['url' => ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten/2', 'besluit' => $besluitUrl, 'informatieobject' => $verborgen],
        ]), 200),
        $besluittypeUrl => Http::response(['url' => $besluittypeUrl, 'omschrijving' => 'Vergunning'], 200),
    ]);

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();
    $zaak = Zaak::factory()->create(['zgw_zaak_url' => $zaakUrl, 'zaaktype_id' => $zaaktype->id]);

    expect($zaak->besluiten)->toHaveCount(1)
        ->and($zaak->besluiten->first()->besluitDocumenten)->toHaveCount(1)
        ->and($zaak->besluiten->first()->besluitDocumenten->first()->vertrouwelijkheidaanduiding)->toBe('zaakvertrouwelijk');
});

test('the tab configuration model is unaffected by these visibility rules', function () {
    // Guard against a regression where showsTab() and the data reads disagree:
    // the flags live on the connection, the visibility rules do not.
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
        'show_besluiten_tab' => false,
    ]);
    // An activated connection and an own-instance zaaktype: the flags only
    // describe a zaak that actually reads from this connection.
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->for($municipality)->create([
            'connection' => "gemeente_{$municipality->id}",
        ])->id,
    ]);

    expect($zaak->showsTab('besluiten'))->toBeFalse();
});
