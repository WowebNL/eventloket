<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\Models\Zaak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Hasht identificerende attributen (BSN, KvK-nummer) in de opgeslagen
 * `form_state_snapshot` en `reference_data` van een Zaak. Vervangt OF's
 * `maybe_hash_identifying_attributes`-task.
 *
 * Draait als laatste in de async-keten — na PDF en email — zodat die
 * eerder nog met de originele waarden kunnen werken. Wat hier overblijft
 * na afloop is: hashes i.p.v. plain BSN/KvK in de DB-snapshot.
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const HASH_PREFIX = 'hash:';

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
