<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\Models\Zaak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hasht identificerende attributen (BSN, KvK-nummer) in de opgeslagen
 * `form_state_snapshot` en `reference_data` van een Zaak. Vervangt OF's
 * `maybe_hash_identifying_attributes`-task.
 *
 * Runs last in the async submit chain, after the PDF and the mail, so those
 * can still work with the original values. When a job earlier in the chain
 * fails for good, the chain's catch callback dispatches this job instead, so
 * the snapshot is hashed either way (see SubmitEventForm::dispatchAsyncChain).
 * What remains afterwards: hashes instead of the plain BSN/KvK in the stored
 * snapshot.
 *
 * Velden die gehashd worden:
 *   - `watIsHetKamerVanKoophandelNummerVanUwOrganisatie` (KvK)
 *   - `bsn` / `auth_bsn`                                 (BSN)
 *
 * Algoritme: HMAC-SHA-256 met een app-specifieke salt uit
 * `APP_KEY`. Dat maakt de hash stabiel over runs heen (dus
 * herkenbaar: "dit is dezelfde KvK als vorige aanvraag") zonder dat
 * attackers met een regenboog-tabel kunnen gokken.
 *
 * Idempotent: een al gehasht veld (prefix `hash:`) wordt niet opnieuw
 * gehasht.
 */
final class HashIdentifyingAttributes implements ShouldQueue
{
    use Queueable;

    /**
     * Marker for an already-hashed value. Public so consumers of the snapshot
     * can recognise a hashed value instead of sending it onward as if it were
     * the original (see InitiatorRolBuilder).
     */
    public const HASH_PREFIX = 'hash:';

    private const GEVOELIGE_SNAPSHOT_KEYS = [
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie',
        'bsn',
        'auth_bsn',
    ];

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        $snapshot = $this->zaak->form_state_snapshot ?? [];
        if (isset($snapshot['values']) && is_array($snapshot['values'])) {
            foreach (self::GEVOELIGE_SNAPSHOT_KEYS as $key) {
                if (array_key_exists($key, $snapshot['values'])) {
                    $snapshot['values'][$key] = $this->hash($snapshot['values'][$key]);
                }
            }
        }

        // `reference_data` heeft geen KvK/BSN in de VO-structuur (zie
        // ZaakReferenceData::toArray()), dus voor nu raken we die niet.
        // Als er later een kolom wordt toegevoegd, breiden we dit uit.

        $this->zaak->forceFill([
            'form_state_snapshot' => $snapshot,
        ])->save();
    }

    /**
     * Whether a stored snapshot still holds one of the identifying values in
     * plain form, i.e. whether running this job on it would change anything.
     * Empty values and values that already carry the hash prefix do not count.
     *
     * @param  array<string, mixed>|null  $snapshot
     */
    public static function hasUnhashedValues(?array $snapshot): bool
    {
        $values = $snapshot['values'] ?? null;
        if (! is_array($values)) {
            return false;
        }

        foreach (self::GEVOELIGE_SNAPSHOT_KEYS as $key) {
            $value = $values[$key] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (is_string($value) && str_starts_with($value, self::HASH_PREFIX)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Geeft een `hash:<hex>`-string terug voor een waarde. Leeg →
     * leeg. Al gehasht → ongewijzigd (idempotent).
     */
    private function hash(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $value;
        }
        if (is_string($value) && str_starts_with($value, self::HASH_PREFIX)) {
            return $value;
        }

        $raw = is_scalar($value) ? (string) $value : (string) json_encode($value);
        $salt = (string) config('app.key', '');

        return self::HASH_PREFIX.hash_hmac('sha256', $raw, $salt);
    }
}
