<?php

declare(strict_types=1);

/**
 * Recognising a vooraankondiging when the municipality runs its own ZGW
 * instance and koppelt the role to one of its own zaaktypen.
 *
 * The local zaaktype row then carries the omschrijving of the external
 * zaaktype, which follows no Eventloket naming convention, so the koppeling
 * is the only source that can tell the role. Detection that leans on the
 * name loses the whole conversion feature for such a municipality: the
 * action on the zaak, the question and the select in the form, the
 * conversion presets on the concepts page and the relation written at
 * submit (which in turn feeds the calendar filter).
 */

use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

/**
 * An external zaaktype koppeld to $role, exactly as the sync leaves it: the
 * name is the external omschrijving and the role column is filled from the
 * koppeling. Pass roleColumn: null for a row the sync has not touched yet.
 */
function gekoppeldZaaktype(
    ZaaktypeRole $role = ZaaktypeRole::Vooraankondiging,
    string $name = 'Activiteit behandelen',
    bool $fillRoleColumn = true,
): Zaaktype {
    $municipality = Municipality::factory()->create();

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => $role,
        'zaaktype_identificatie' => 'EXT-1',
    ]);

    return Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'identificatie' => 'EXT-1',
        'name' => $name,
        'role' => $fillRoleColumn ? $role : null,
        'is_active' => true,
    ]);
}

function zaakOp(Zaaktype $zaaktype): Zaak
{
    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => Organisation::factory()->create()->id,
    ]);
}

it('recognises a koppeld vooraankondiging whose name does not follow the convention', function () {
    $zaaktype = gekoppeldZaaktype();

    expect($zaaktype->name)->not->toStartWith('Vooraankondiging')
        ->and($zaaktype->effectiveRole())->toBe(ZaaktypeRole::Vooraankondiging)
        ->and($zaaktype->isVooraankondiging())->toBeTrue()
        ->and(zaakOp($zaaktype)->isVooraankondiging())->toBeTrue();
});

it('recognises a koppeld vooraankondiging before the sync filled the role column', function () {
    // The koppeling is the first rung of the ladder, so the row does not have
    // to be refreshed before the conversion works.
    $zaaktype = gekoppeldZaaktype(fillRoleColumn: false);

    expect($zaaktype->isVooraankondiging())->toBeTrue();
});

it('does not treat a zaaktype koppeld to another role as a vooraankondiging', function () {
    // The koppeling wins over the name: an external omschrijving that happens
    // to start with the Eventloket prefix does not make this a vooraankondiging.
    $zaaktype = gekoppeldZaaktype(role: ZaaktypeRole::Melding, name: 'Vooraankondiging activiteit');

    expect($zaaktype->isVooraankondiging())->toBeFalse()
        ->and(zaakOp($zaaktype)->isVooraankondiging())->toBeFalse();
});

it('keeps the shared-catalogus naming convention for rows without a koppeling', function () {
    $municipality = Municipality::factory()->create();

    $vooraankondiging = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Vooraankondiging gemeente Test',
        'role' => null,
        'is_active' => true,
    ]);
    $vergunning = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Evenementenvergunning gemeente Test',
        'role' => null,
        'is_active' => true,
    ]);

    expect($vooraankondiging->isVooraankondiging())->toBeTrue()
        ->and($vergunning->isVooraankondiging())->toBeFalse();
});

it('prefers the role column over the name for rows without a koppeling', function () {
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => Municipality::factory()->create()->id,
        'name' => 'Vooraankondiging gemeente Test',
        'role' => ZaaktypeRole::Melding,
        'is_active' => true,
    ]);

    expect($zaaktype->isVooraankondiging())->toBeFalse();
});

it('selects koppelde vooraankondigingen with the query scope', function () {
    $vooraankondiging = zaakOp(gekoppeldZaaktype());
    $melding = zaakOp(gekoppeldZaaktype(role: ZaaktypeRole::Melding, name: 'Vooraankondiging activiteit'));

    expect(Zaak::query()->vooraankondigingen()->pluck('id')->all())->toBe([$vooraankondiging->id])
        ->and(Zaak::query()->vooraankondigingen()->whereKey($melding->id)->exists())->toBeFalse();
});

it('keeps selecting name-convention vooraankondigingen with the query scope', function () {
    $municipality = Municipality::factory()->create();

    $byName = zaakOp(Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Vooraankondiging gemeente Test',
        'role' => null,
        'is_active' => true,
    ]));
    $byRole = zaakOp(Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Externe omschrijving',
        'role' => ZaaktypeRole::Vooraankondiging,
        'is_active' => true,
    ]));
    zaakOp(Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Evenementenvergunning gemeente Test',
        'role' => null,
        'is_active' => true,
    ]));

    expect(Zaak::query()->vooraankondigingen()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$byName->id, $byRole->id])->sort()->values()->all());
});

it('leaves a zaaktype without municipality out of the koppeling lookup', function () {
    // A row that is not linked to a municipality can never match a koppeling,
    // so the ladder has to fall through to the role column and the name.
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => null,
        'identificatie' => 'EXT-1',
        'name' => 'Vooraankondiging gemeente Test',
        'role' => null,
        'is_active' => true,
    ]);

    $zaak = zaakOp($zaaktype);

    expect($zaaktype->isVooraankondiging())->toBeTrue()
        ->and(Zaak::query()->vooraankondigingen()->pluck('id')->all())->toBe([$zaak->id]);
});

it('ends up with a name that defeats the naming convention after a real sync', function () {
    // The precondition of the whole scenario, driven through the actual sync:
    // saving the koppeling refreshes the local row, which takes its name from
    // the external catalogus.
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $municipality->id]);

    $base = 'https://gemeente.example.com';
    Http::fake([
        "{$base}/catalogi/api/v1/zaaktypen*" => Http::response(ZgwHttpFake::envelope([
            [
                'url' => "{$base}/catalogi/api/v1/zaaktypen/1",
                'identificatie' => 'EXT-1',
                'omschrijving' => 'Activiteit behandelen',
            ],
        ])),
        "{$base}/*" => Http::response(ZgwHttpFake::envelope([])),
    ]);

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vooraankondiging,
        'zaaktype_identificatie' => 'EXT-1',
    ]);

    $zaaktype = Zaaktype::query()->where('identificatie', 'EXT-1')->sole();

    expect($zaaktype->name)->toBe('Activiteit behandelen')
        ->and($zaaktype->isVooraankondiging())->toBeTrue();
});

it('reads the naming convention case-insensitively in PHP and in SQL alike', function () {
    // ZaaktypeRole::fromName() lowercases both sides of the prefix comparison,
    // so the SQL rung has to as well. A plain `like` does not: it is
    // case-sensitive on PostgreSQL and case-insensitive on MySQL, which would
    // let the same row answer differently per engine and differently from the
    // model it is supposed to mirror.
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => Municipality::factory()->create()->id,
        'name' => 'vooraankondiging gemeente Test',
        'role' => null,
        'is_active' => true,
    ]);

    $zaak = zaakOp($zaaktype);

    expect($zaaktype->isVooraankondiging())->toBeTrue()
        ->and(Zaak::query()->vooraankondigingen()->pluck('id')->all())->toBe([$zaak->id]);
});
