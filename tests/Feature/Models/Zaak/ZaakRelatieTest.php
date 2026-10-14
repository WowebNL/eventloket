<?php

/**
 * Datamodel tests for the generic typed relation table `zaak_relaties`
 * (issue #10). Covers the guards (self-reference, uniqueness), the
 * generic relations and the named helpers for the
 * `vervangt_vooraankondiging` type in both directions, including the
 * soft-delete behaviour of the successor.
 */

use App\Enums\ZaakRelatieType;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\ZaakRelatie;
use App\Models\Zaaktype;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function maakZaakMetZaaktypeNaam(string $zaaktypeNaam): Zaak
{
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => $zaaktypeNaam,
        'is_active' => true,
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => Organisation::factory()->create()->id,
    ]);
}

test('een relatie met zichzelf wordt geweigerd', function () {
    $zaak = maakZaakMetZaaktypeNaam('Evenementenvergunning gemeente Test');

    ZaakRelatie::create([
        'zaak_id' => $zaak->id,
        'gerelateerde_zaak_id' => $zaak->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);
})->throws(InvalidArgumentException::class);

test('dezelfde relatie kan niet twee keer bestaan', function () {
    $aanvraag = maakZaakMetZaaktypeNaam('Evenementenvergunning gemeente Test');
    $vooraankondiging = maakZaakMetZaaktypeNaam('Vooraankondiging gemeente Test');

    ZaakRelatie::create([
        'zaak_id' => $aanvraag->id,
        'gerelateerde_zaak_id' => $vooraankondiging->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);

    ZaakRelatie::create([
        'zaak_id' => $aanvraag->id,
        'gerelateerde_zaak_id' => $vooraankondiging->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);
})->throws(QueryException::class);

test('de benoemde helpers lezen de relatie in beide richtingen', function () {
    $aanvraag = maakZaakMetZaaktypeNaam('Evenementenvergunning gemeente Test');
    $vooraankondiging = maakZaakMetZaaktypeNaam('Vooraankondiging gemeente Test');

    ZaakRelatie::create([
        'zaak_id' => $aanvraag->id,
        'gerelateerde_zaak_id' => $vooraankondiging->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);

    expect($aanvraag->vervangtVooraankondiging()->pluck('zaken.id')->all())->toBe([$vooraankondiging->id])
        ->and($vooraankondiging->opgevolgdDoor()->pluck('zaken.id')->all())->toBe([$aanvraag->id])
        ->and($aanvraag->relaties()->count())->toBe(1)
        ->and($vooraankondiging->inverseRelaties()->count())->toBe(1);
});

test('een soft-deleted opvolger telt niet meer mee als opvolging', function () {
    $aanvraag = maakZaakMetZaaktypeNaam('Evenementenvergunning gemeente Test');
    $vooraankondiging = maakZaakMetZaaktypeNaam('Vooraankondiging gemeente Test');

    ZaakRelatie::create([
        'zaak_id' => $aanvraag->id,
        'gerelateerde_zaak_id' => $vooraankondiging->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);

    $aanvraag->delete();

    // The relation row survives a soft delete (the FK cascade only fires
    // on hard deletes), but the successor no longer shows up.
    expect(ZaakRelatie::count())->toBe(1)
        ->and($vooraankondiging->refresh()->opgevolgdDoor()->count())->toBe(0);
});

test('een hard-deleted opvolger ruimt de relatierij op via de FK-cascade', function () {
    $aanvraag = maakZaakMetZaaktypeNaam('Evenementenvergunning gemeente Test');
    $vooraankondiging = maakZaakMetZaaktypeNaam('Vooraankondiging gemeente Test');

    ZaakRelatie::create([
        'zaak_id' => $aanvraag->id,
        'gerelateerde_zaak_id' => $vooraankondiging->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);

    $aanvraag->forceDelete();

    expect(ZaakRelatie::count())->toBe(0);
});

test('isVooraankondiging volgt de naamconventie van het zaaktype', function () {
    expect(maakZaakMetZaaktypeNaam('Vooraankondiging gemeente Heerlen')->isVooraankondiging())->toBeTrue()
        ->and(maakZaakMetZaaktypeNaam('Evenementenvergunning gemeente Heerlen')->isVooraankondiging())->toBeFalse()
        ->and(maakZaakMetZaaktypeNaam('Melding evenement gemeente Heerlen')->isVooraankondiging())->toBeFalse();
});

test('de vooraankondigingen-scope selecteert alleen vooraankondigingen', function () {
    $vooraankondiging = maakZaakMetZaaktypeNaam('Vooraankondiging gemeente Heerlen');
    maakZaakMetZaaktypeNaam('Evenementenvergunning gemeente Heerlen');

    expect(Zaak::query()->vooraankondigingen()->pluck('id')->all())->toBe([$vooraankondiging->id]);
});

test('de nogNietOpgevolgd-scope sluit vooraankondigingen met een definitieve aanvraag uit', function () {
    $gekoppeld = maakZaakMetZaaktypeNaam('Vooraankondiging gemeente Heerlen');
    $vrij = maakZaakMetZaaktypeNaam('Vooraankondiging gemeente Maastricht');
    $aanvraag = maakZaakMetZaaktypeNaam('Evenementenvergunning gemeente Heerlen');

    ZaakRelatie::create([
        'zaak_id' => $aanvraag->id,
        'gerelateerde_zaak_id' => $gekoppeld->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);

    expect(Zaak::query()->vooraankondigingen()->nogNietOpgevolgd()->pluck('id')->all())->toBe([$vrij->id]);

    // A soft-deleted successor no longer blocks a new link.
    $aanvraag->delete();

    expect(Zaak::query()->vooraankondigingen()->nogNietOpgevolgd()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$gekoppeld->id, $vrij->id])->sort()->values()->all());
});
