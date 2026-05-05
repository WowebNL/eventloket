<?php

/**
 * Na submit moet het BSN / KvK-nummer van de aanvrager niet meer in
 * platte tekst in de opgeslagen FormState-snapshot staan — dat is te
 * risicovol onder de AVG als die DB ooit lekt. OF had hiervoor
 * `maybe_hash_identifying_attributes`; wij doen hetzelfde in
 * `HashIdentifyingAttributes`.
 *
 * Deze tests controleren:
 *
 *   - Na uitvoering zijn KvK en BSN vervangen door een stabiele hash
 *     (prefix `hash:`), géén leesbare waarde meer.
 *   - Niet-gevoelige velden (bv. naam evenement) blijven ongewijzigd.
 *   - Dezelfde waarde levert dezelfde hash → twee aanvragen van
 *     dezelfde KvK zijn koppelbaar zonder dat we de KvK bewaren.
 *   - De job is idempotent: opnieuw uitvoeren dubbelhasht niet.
 *   - Lege/ontbrekende velden worden genegeerd, niet vervangen door
 *     een betekenisloze hash van een lege string.
 */

use App\Jobs\Submit\HashIdentifyingAttributes;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function zaakMetFormStateValues(array $values): Zaak
{
    // Minimaal zaaktype + gemeente zodat ZaakObserver's
    // `CreateConceptAdviceQuestions` niet op null trips.
    $muni = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $muni->id,
        'is_active' => true,
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'form_state_snapshot' => [
            'values' => $values,
            'system' => [],
            'field_hidden' => [],
            'step_applicable' => [],
        ],
    ]);
}

test('KvK en BSN worden gehashed in de snapshot, overige velden niet', function () {
    $zaak = zaakMetFormStateValues([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'bsn' => '123456789',
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
    ]);

    (new HashIdentifyingAttributes($zaak))->handle();

    $fresh = $zaak->fresh()->form_state_snapshot['values'];

    expect($fresh['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'])
        ->toStartWith('hash:')
        ->not->toBe('12345678');

    expect($fresh['bsn'])
        ->toStartWith('hash:')
        ->not->toBe('123456789');

    // Overig veld blijft onaangeroerd.
    expect($fresh['watIsDeNaamVanHetEvenementVergunning'])
        ->toBe('Buurtfeest Testlaan');
});

test('dezelfde KvK krijgt dezelfde hash → twee aanvragen zijn koppelbaar', function () {
    $zaak1 = zaakMetFormStateValues([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
    ]);
    $zaak2 = zaakMetFormStateValues([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
    ]);

    (new HashIdentifyingAttributes($zaak1))->handle();
    (new HashIdentifyingAttributes($zaak2))->handle();

    $h1 = $zaak1->fresh()->form_state_snapshot['values']['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'];
    $h2 = $zaak2->fresh()->form_state_snapshot['values']['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'];

    expect($h1)->toBe($h2);
});

test('opnieuw runnen hasht een al gehasht veld niet dubbel', function () {
    $zaak = zaakMetFormStateValues([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
    ]);

    (new HashIdentifyingAttributes($zaak))->handle();
    $eersteHash = $zaak->fresh()->form_state_snapshot['values']['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'];

    (new HashIdentifyingAttributes($zaak->fresh()))->handle();
    $tweedeHash = $zaak->fresh()->form_state_snapshot['values']['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'];

    expect($tweedeHash)->toBe($eersteHash);
});

test('lege of ontbrekende velden worden niet stilletjes vervangen door een hash', function () {
    $zaak = zaakMetFormStateValues([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '',
        // bsn ontbreekt volledig
    ]);

    (new HashIdentifyingAttributes($zaak))->handle();

    $fresh = $zaak->fresh()->form_state_snapshot['values'];

    expect($fresh['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'])->toBe('');
    expect($fresh)->not->toHaveKey('bsn');
});
