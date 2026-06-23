<?php

declare(strict_types=1);

namespace App\EventForm\Persistence;

use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Persistence-laag voor de concept-opslag van het evenementformulier.
 * Wraps de `Draft`-eloquent zodat de Filament-pages niet direct met
 * DB-records rommelen. Een gebruiker kan per organisatie meerdere
 * concepten hebben, gecapt op MAX_DRAFTS; concepten die EXPIRY_MONTHS
 * niet zijn aangeraakt worden opgeruimd.
 */
class DraftStore
{
    public const MAX_DRAFTS = 5;

    public const EXPIRY_MONTHS = 6;

    /** Veld-key waaruit de weergavenaam van een concept wordt afgeleid. */
    private const NAME_FIELD = 'watIsDeNaamVanHetEvenementVergunning';

    /**
     * Alle concepten van deze gebruiker binnen deze organisatie, meest
     * recent bewerkt eerst. Ruimt eerst verlopen concepten op zodat de
     * lijst nooit concepten toont die op het punt van verdwijnen staan.
     *
     * @return Collection<int, Draft>
     */
    public function listFor(User $user, Organisation $organisation): Collection
    {
        $this->pruneExpired($user, $organisation);

        return Draft::query()
            ->ownedBy($user, $organisation)
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Ownership-scoped lookup: geeft null terug bij een onbekend id óf
     * een concept van een andere gebruiker/organisatie, zodat callers
     * geen onderscheid (en dus geen informatie-lek) hebben.
     */
    public function findFor(User $user, Organisation $organisation, int|string $draftId): ?Draft
    {
        if (! is_numeric($draftId)) {
            return null;
        }

        /** @var Draft|null $draft */
        $draft = Draft::query()
            ->ownedBy($user, $organisation)
            ->whereKey((int) $draftId)
            ->first();

        return $draft;
    }

    public function hasCapacity(User $user, Organisation $organisation): bool
    {
        return Draft::query()->ownedBy($user, $organisation)->count() < self::MAX_DRAFTS;
    }

    /**
     * Maak een nieuw concept aan. Een bestaand concept zonder ingevulde
     * velden wordt hergebruikt zodat herhaald "Start nieuwe aanvraag"
     * geen lege junk-rijen stapelt — behalve wanneer de nieuwe state
     * zelf al gevuld is (prefill), dan is een eigen rij gewenst.
     *
     * @throws DraftLimitReached wanneer MAX_DRAFTS is bereikt
     */
    public function create(
        User $user,
        Organisation $organisation,
        FormState $state,
        ?string $currentStepKey = null,
    ): Draft {
        if ($state->fields() === []) {
            // In PHP filteren i.p.v. een JSON-query: een lege values-bag
            // serialiseert als `[]` maar een gevulde als object, waardoor
            // bv. jsonb_array_length() op Postgres zou breken. Het zijn
            // hooguit MAX_DRAFTS rijen, dus dit is goedkoop.
            $existingEmpty = Draft::query()
                ->ownedBy($user, $organisation)
                ->get()
                ->first(fn (Draft $draft): bool => empty($draft->state['values'] ?? []));

            if ($existingEmpty instanceof Draft) {
                return $existingEmpty;
            }
        }

        if (! $this->hasCapacity($user, $organisation)) {
            throw new DraftLimitReached;
        }

        return Draft::create([
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
            'state' => $state->toSnapshot(),
            'name' => $this->deriveName($state),
            'current_step_key' => $currentStepKey,
        ]);
    }

    /**
     * Persisteer state + huidige stap en werk de afgeleide weergavenaam bij.
     */
    public function save(Draft $draft, FormState $state, ?string $currentStepKey): void
    {
        $draft->update([
            'state' => $state->toSnapshot(),
            'name' => $this->deriveName($state),
            'current_step_key' => $currentStepKey,
        ]);
    }

    /**
     * Goedkope update van alleen de huidige stap (stap-navigatie zonder
     * veldwijziging hoeft niet de hele state-JSON opnieuw te schrijven).
     */
    public function saveStep(Draft $draft, ?string $currentStepKey): void
    {
        $draft->update(['current_step_key' => $currentStepKey]);
    }

    public function delete(Draft $draft): void
    {
        $draft->delete();
    }

    /**
     * Verwijder concepten die EXPIRY_MONTHS niet zijn bewerkt. Zonder
     * argumenten globaal (voor de scheduled job); met user+organisation
     * gescoped (defensieve opruiming vóór het tonen van de lijst).
     *
     * @return int aantal verwijderde concepten
     */
    public function pruneExpired(?User $user = null, ?Organisation $organisation = null): int
    {
        $query = Draft::query()
            ->where('updated_at', '<', now()->subMonths(self::EXPIRY_MONTHS));

        if ($user !== null && $organisation !== null) {
            $query->ownedBy($user, $organisation);
        }

        return $query->delete();
    }

    private function deriveName(FormState $state): ?string
    {
        $name = $state->get(self::NAME_FIELD);
        if (! is_string($name)) {
            return null;
        }

        $name = trim($name);

        return $name === '' ? null : mb_substr($name, 0, 255);
    }
}
