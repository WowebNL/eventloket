<?php

/**
 * Server-side re-checks of the vooraankondiging link on submit
 * (issue #10). The form values come from the client, so the step must
 * only write the relation when:
 *   - the organiser answered "Ja" and selected a vooraankondiging,
 *   - the aanvraag resolves to the vergunning path (answer 8),
 *   - the source belongs to the same organisation,
 *   - the source actually is a vooraankondiging.
 * A failing guard skips the link without failing the submit.
 */

use App\Enums\ZaakRelatieType;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\EventForm\State\FormState;
use App\EventForm\Submit\Steps\KoppelVooraankondiging;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\ZaakRelatie;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function koppelScenario(string $bronZaaktypeNaam = 'Vooraankondiging gemeente Heerlen', ?Organisation $bronOrganisation = null): array
{
    $organisation = Organisation::factory()->create();
    $municipality = Municipality::factory()->create();

    $bron = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => $municipality->id,
            'name' => $bronZaaktypeNaam,
            'is_active' => true,
        ])->id,
        'organisation_id' => ($bronOrganisation ?? $organisation)->id,
    ]);

    $aanvraag = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => $municipality->id,
            'name' => 'Evenementenvergunning gemeente Heerlen',
            'is_active' => true,
        ])->id,
        'organisation_id' => $organisation->id,
    ]);

    return compact('organisation', 'bron', 'aanvraag');
}

function vergunningStateMetKoppeling(?string $bronId, string $heeftVooraankondiging = 'Ja'): FormState
{
    return new FormState(values: [
        // Legacy path resolving to a vergunningaanvraag.
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD => $heeftVooraankondiging,
        Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD => $bronId,
    ]);
}

test('schrijft de relatie bij een gekoppelde vooraankondiging op het vergunningpad', function () {
    $sc = koppelScenario();

    app(KoppelVooraankondiging::class)->execute(
        vergunningStateMetKoppeling($sc['bron']->id),
        $sc['aanvraag'],
        $sc['organisation'],
    );

    $relatie = ZaakRelatie::sole();
    expect($relatie->zaak_id)->toBe($sc['aanvraag']->id)
        ->and($relatie->gerelateerde_zaak_id)->toBe($sc['bron']->id)
        ->and($relatie->type)->toBe(ZaakRelatieType::VervangtVooraankondiging);
});

test('is idempotent bij een tweede aanroep', function () {
    $sc = koppelScenario();
    $state = vergunningStateMetKoppeling($sc['bron']->id);

    app(KoppelVooraankondiging::class)->execute($state, $sc['aanvraag'], $sc['organisation']);
    app(KoppelVooraankondiging::class)->execute($state, $sc['aanvraag'], $sc['organisation']);

    expect(ZaakRelatie::count())->toBe(1);
});

test('koppelt ook wanneer de vooraankondiging al is afgesloten', function () {
    // Answer 6: in practice the vooraankondiging is usually already
    // finished by the time the definitive aanvraag arrives; a resultaat
    // must not block the link.
    $sc = koppelScenario();
    $referenceData = $sc['bron']->reference_data->toArray();
    $referenceData['resultaat'] = 'Afgehandeld';
    $sc['bron']->update(['reference_data' => new ZaakReferenceData(...$referenceData)]);

    app(KoppelVooraankondiging::class)->execute(
        vergunningStateMetKoppeling($sc['bron']->id),
        $sc['aanvraag'],
        $sc['organisation'],
    );

    expect(ZaakRelatie::count())->toBe(1);
});

test('koppelt niet wanneer de organisator "Nee" heeft geantwoord', function () {
    $sc = koppelScenario();

    app(KoppelVooraankondiging::class)->execute(
        vergunningStateMetKoppeling($sc['bron']->id, heeftVooraankondiging: 'Nee'),
        $sc['aanvraag'],
        $sc['organisation'],
    );

    expect(ZaakRelatie::count())->toBe(0);
});

test('koppelt niet op het meldingpad', function () {
    // Answer 8: only a vergunningaanvraag replaces a vooraankondiging.
    $sc = koppelScenario();

    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee', // melding
        Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD => 'Ja',
        Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD => $sc['bron']->id,
    ]);

    app(KoppelVooraankondiging::class)->execute($state, $sc['aanvraag'], $sc['organisation']);

    expect(ZaakRelatie::count())->toBe(0);
});

test('koppelt niet aan een zaak van een andere organisatie', function () {
    $sc = koppelScenario(bronOrganisation: Organisation::factory()->create());

    app(KoppelVooraankondiging::class)->execute(
        vergunningStateMetKoppeling($sc['bron']->id),
        $sc['aanvraag'],
        $sc['organisation'],
    );

    expect(ZaakRelatie::count())->toBe(0);
});

test('koppelt niet aan een bron die geen vooraankondiging is', function () {
    $sc = koppelScenario(bronZaaktypeNaam: 'Melding evenement gemeente Heerlen');

    app(KoppelVooraankondiging::class)->execute(
        vergunningStateMetKoppeling($sc['bron']->id),
        $sc['aanvraag'],
        $sc['organisation'],
    );

    expect(ZaakRelatie::count())->toBe(0);
});

test('koppelt niet zonder geselecteerde vooraankondiging', function () {
    $sc = koppelScenario();

    app(KoppelVooraankondiging::class)->execute(
        vergunningStateMetKoppeling(null),
        $sc['aanvraag'],
        $sc['organisation'],
    );

    expect(ZaakRelatie::count())->toBe(0);
});

test('koppelt niet aan een vooraankondiging die al een definitieve aanvraag heeft', function () {
    $sc = koppelScenario();

    // An earlier definitive aanvraag already replaced this vooraankondiging.
    $eerdereAanvraag = Zaak::factory()->create([
        'zaaktype_id' => $sc['aanvraag']->zaaktype_id,
        'organisation_id' => $sc['organisation']->id,
    ]);
    ZaakRelatie::create([
        'zaak_id' => $eerdereAanvraag->id,
        'gerelateerde_zaak_id' => $sc['bron']->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);

    app(KoppelVooraankondiging::class)->execute(
        vergunningStateMetKoppeling($sc['bron']->id),
        $sc['aanvraag'],
        $sc['organisation'],
    );

    expect(ZaakRelatie::count())->toBe(1)
        ->and(ZaakRelatie::sole()->zaak_id)->toBe($eerdereAanvraag->id);
});

test('koppelt wél opnieuw wanneer de eerdere opvolger soft-deleted is', function () {
    $sc = koppelScenario();

    $eerdereAanvraag = Zaak::factory()->create([
        'zaaktype_id' => $sc['aanvraag']->zaaktype_id,
        'organisation_id' => $sc['organisation']->id,
    ]);
    ZaakRelatie::create([
        'zaak_id' => $eerdereAanvraag->id,
        'gerelateerde_zaak_id' => $sc['bron']->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);
    $eerdereAanvraag->delete();

    app(KoppelVooraankondiging::class)->execute(
        vergunningStateMetKoppeling($sc['bron']->id),
        $sc['aanvraag'],
        $sc['organisation'],
    );

    expect(ZaakRelatie::where('zaak_id', $sc['aanvraag']->id)->count())->toBe(1);
});
