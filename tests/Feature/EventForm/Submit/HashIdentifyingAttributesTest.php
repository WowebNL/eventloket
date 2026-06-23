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
 *   - De activity-log bevat geen form_state_snapshot (AVG: geen BSN/KvK
 *     in audit-trail, ook niet via Spatie ActivityLog).
 */

use App\Jobs\Submit\HashIdentifyingAttributes;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

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

test('activity-log bevat geen form_state_snapshot zodat BSN/KvK nooit in de audit-trail belanden', function () {
    $zaak = zaakMetFormStateValues([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'bsn' => '123456789',
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
    ]);

    // Verifieer dat de 'created'-log-entry geen form_state_snapshot bevat.
    $logEntry = Activity::where('subject_type', Zaak::class)
        ->where('subject_id', $zaak->id)
        ->where('event', 'created')
        ->first();

    expect($logEntry)->not->toBeNull();

    $properties = $logEntry->properties->toArray();
    $attributes = $properties['attributes'] ?? [];

    expect($attributes)->not->toHaveKey('form_state_snapshot',
        'form_state_snapshot mag nooit in de activity-log staan — bevat mogelijk plain BSN/KvK'
    );
});

test('activity-log bevat geen form_state_snapshot na HashIdentifyingAttributes', function () {
    $zaak = zaakMetFormStateValues([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'bsn' => '123456789',
    ]);

    (new HashIdentifyingAttributes($zaak))->handle();

    // De update-log-entry (door forceFill + save in de job) mag ook
    // geen form_state_snapshot bevatten — niet in 'attributes' en niet
    // in 'old' (want dat zou de plaintext-waarde blootgeven).
    $logEntries = Activity::where('subject_type', Zaak::class)
        ->where('subject_id', $zaak->id)
        ->get();

    foreach ($logEntries as $entry) {
        $properties = $entry->properties->toArray();

        expect($properties['attributes'] ?? [])->not->toHaveKey('form_state_snapshot',
            "activity-log entry '{$entry->event}' bevat form_state_snapshot in attributes"
        );
        expect($properties['old'] ?? [])->not->toHaveKey('form_state_snapshot',
            "activity-log entry '{$entry->event}' bevat form_state_snapshot in old"
        );
    }
});
