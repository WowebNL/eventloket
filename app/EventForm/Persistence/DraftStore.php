<?php

declare(strict_types=1);

namespace App\EventForm\Persistence;

use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\User;

/**
 * Persistence-laag voor de concept-opslag van het evenementformulier.
 * Wraps de `Draft`-eloquent zodat de Filament-page niet direct met
 * DB-records rommelt.
 */
class DraftStore
{
    public function load(User $user, Organisation $organisation): ?FormState
    {
        $draft = $this->find($user, $organisation);

        if ($draft === null) {
            return null;
        }

        return FormState::fromSnapshot($draft->state ?? []);
    }

    public function save(
        User $user,
        Organisation $organisation,
        FormState $state,
        ?string $currentStepKey,
    ): void {
        Draft::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'organisation_id' => $organisation->id,
            ],
            [
                'state' => $state->toSnapshot(),
                'current_step_key' => $currentStepKey,
            ],
        );
    }

    public function clear(User $user, Organisation $organisation): void
    {
        Draft::query()
            ->where('user_id', $user->id)
            ->where('organisation_id', $organisation->id)
            ->delete();
    }

    public function currentStepKey(User $user, Organisation $organisation): ?string
    {
        return $this->find($user, $organisation)?->current_step_key;
    }

    private function find(User $user, Organisation $organisation): ?Draft
    {
        /** @var Draft|null $draft */
        $draft = Draft::query()
            ->where('user_id', $user->id)
            ->where('organisation_id', $organisation->id)
            ->first();

        return $draft;
    }
}
