<?php

/**
 * UpdateInitiatorZGW sets the initiator rol on the ZGW zaak from the initiator
 * block in the FormState snapshot.
 *
 * Main behaviour covered here: for an organisation with a KvK number the rol
 * depends on the connection. Our own OpenZaak keeps the
 * `niet_natuurlijk_persoon` payload it has always received, in which
 * `annIdentificatie` carries the company number (the Zaken API defines no
 * `kvkNummer` on RolNietNatuurlijkPersoon in any release from 1.0 up to and
 * including 1.7) and the non-standard `kvkNummer` rides along. Every other
 * connection gets a `vestiging` rol, the only betrokkeneType that defines a
 * `kvkNummer` property, so the number can actually be stored. For a private
 * aanvrager (no KvK) the rol carries a stable `anpIdentificatie` and the
 * verblijfsadres from the form's address fieldset, so backends such as
 * OneGround materialise a visible betrokkene.
 */

use App\Enums\Role;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Jobs\Zaak\UpdateInitiatorZGW;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

const MUNICIPALITY_HOST = 'https://gemeente.example.com';

function zaakMetInitiator(array $values, ?int $organiserUserId = null): Zaak
{
    $muni = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $muni->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/abc-123',
        'form_state_snapshot' => ['values' => $values],
        'organiser_user_id' => $organiserUserId,
    ]);
}

/** The same zaak, but on a municipality running its own ZGW instance. */
function zaakMetInitiatorOpEigenInstance(array $values): Zaak
{
    $muni = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $muni->id]);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $muni->id,
        'connection' => "gemeente_{$muni->id}",
        'zgw_zaaktype_url' => MUNICIPALITY_HOST.'/catalogi/api/v1/zaaktypen/1',
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => MUNICIPALITY_HOST.'/zaken/api/v1/zaken/abc-123',
        'form_state_snapshot' => ['values' => $values],
    ]);
}

function fakeZaakRoltypenEnRollen(): void
{
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/abc-123';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen/1', 'omschrijvingGeneriek' => 'initiator'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen' => Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen/1'], 201),
        $zaakUrl.'*' => Http::response([
            'url' => $zaakUrl,
            'uuid' => 'abc-123',
            'identificatie' => 'ZAAK-123',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
            'omschrijving' => 'Test',
            'startdatum' => '2026-06-26',
            'registratiedatum' => '2026-06-26',
            'einddatum' => null,
            'einddatumGepland' => null,
            'uiterlijkeEinddatumAfdoening' => null,
            'bronorganisatie' => '820151130',
            'zaakgeometrie' => null,
        ], 200),
    ]);
}

function fakeZaakRoltypenEnRollenOpEigenInstance(): void
{
    $zaakUrl = MUNICIPALITY_HOST.'/zaken/api/v1/zaken/abc-123';

    Http::fake([
        MUNICIPALITY_HOST.'/catalogi/api/v1/roltypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => MUNICIPALITY_HOST.'/catalogi/api/v1/roltypen/1', 'omschrijvingGeneriek' => 'initiator'],
        ]), 200),
        MUNICIPALITY_HOST.'/zaken/api/v1/rollen' => Http::response(['url' => MUNICIPALITY_HOST.'/zaken/api/v1/rollen/1'], 201),
        $zaakUrl.'*' => Http::response([
            'url' => $zaakUrl,
            'uuid' => 'abc-123',
            'identificatie' => 'ZAAK-123',
            'zaaktype' => MUNICIPALITY_HOST.'/catalogi/api/v1/zaaktypen/1',
            'omschrijving' => 'Test',
            'startdatum' => '2026-06-26',
            'registratiedatum' => '2026-06-26',
            'einddatum' => null,
            'einddatumGepland' => null,
            'uiterlijkeEinddatumAfdoening' => null,
            'bronorganisatie' => '820151130',
            'zaakgeometrie' => null,
        ], 200),
    ]);
}

test('stuurt voor een organisatie op een gemeente-instance een vestiging-rol met kvkNummer en handelsnaam', function () {
    $zaak = zaakMetInitiatorOpEigenInstance([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'watIsDeNaamVanUwOrganisatie' => 'Acme BV',
    ]);

    fakeZaakRoltypenEnRollenOpEigenInstance();

    (new UpdateInitiatorZGW($zaak))->handle(app(ZaakeigenschappenMap::class));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/zaken/api/v1/rollen') || $request->method() !== 'POST') {
            return false;
        }

        $identificatie = $request->data()['betrokkeneIdentificatie'] ?? [];

        return $request->data()['betrokkeneType'] === 'vestiging'
            && $request->data()['roltoelichting'] === 'inzender formulier'
            && ($identificatie['kvkNummer'] ?? null) === '12345678'
            && ($identificatie['handelsnaam'] ?? null) === ['Acme BV']
            && ! array_key_exists('annIdentificatie', $identificatie)
            && ! array_key_exists('statutaireNaam', $identificatie);
    });
});

test('stuurt voor een organisatie annIdentificatie naast kvkNummer mee', function () {
    $zaak = zaakMetInitiator([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'watIsDeNaamVanUwOrganisatie' => 'Acme BV',
    ]);

    fakeZaakRoltypenEnRollen();

    (new UpdateInitiatorZGW($zaak))->handle(app(ZaakeigenschappenMap::class));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/zaken/api/v1/rollen') || $request->method() !== 'POST') {
            return false;
        }

        $identificatie = $request->data()['betrokkeneIdentificatie'] ?? [];

        return $request->data()['betrokkeneType'] === 'niet_natuurlijk_persoon'
            && ($identificatie['annIdentificatie'] ?? null) === '12345678'
            && ($identificatie['kvkNummer'] ?? null) === '12345678'
            && ($identificatie['statutaireNaam'] ?? null) === 'Acme BV';
    });
});

test('stuurt voor een particulier anpIdentificatie, naam en verblijfsadres mee', function () {
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $zaak = zaakMetInitiator([
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
        'watIsUwEMailadres' => 'jan@example.com',
        'postcode' => '6411CD',
        'huisnummer' => '32',
        'straatnaam' => 'Coriovallumstraat',
        'plaatsnaam' => 'Heerlen',
    ], $user->id);

    fakeZaakRoltypenEnRollen();

    (new UpdateInitiatorZGW($zaak))->handle(app(ZaakeigenschappenMap::class));

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/zaken/api/v1/rollen') || $request->method() !== 'POST') {
            return false;
        }

        $identificatie = $request->data()['betrokkeneIdentificatie'] ?? [];
        $adres = $identificatie['verblijfsadres'] ?? [];

        return $request->data()['betrokkeneType'] === 'natuurlijk_persoon'
            && ($request->data()['afwijkendeNaamBetrokkene'] ?? null) === 'Jan Jansen'
            && ($identificatie['anpIdentificatie'] ?? null) === "EVL{$user->id}"
            && ($identificatie['geslachtsnaam'] ?? null) === 'Jansen'
            && ($identificatie['voornamen'] ?? null) === 'Jan'
            && ($adres['aoaPostcode'] ?? null) === '6411CD'
            && ($adres['aoaHuisnummer'] ?? null) === 32
            && ($adres['gorOpenbareRuimteNaam'] ?? null) === 'Coriovallumstraat'
            && ($adres['wplWoonplaatsNaam'] ?? null) === 'Heerlen';
    });
});

test('zonder zgw_zaak_url gebeurt er niets', function () {
    $zaak = zaakMetInitiator([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
    ]);
    $zaak->zgw_zaak_url = null;
    $zaak->save();

    Http::fake();

    (new UpdateInitiatorZGW($zaak))->handle(app(ZaakeigenschappenMap::class));

    Http::assertNothingSent();
});
