<?php

declare(strict_types=1);

namespace App\EventForm\Persistence;

use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Arr;
use Woweb\Openzaak\ObjectsApi;

/**
 * Haalt een prefill-record uit de Objects API (gebruikt bij het hergebruiken
 * van een eerdere aanvraag via de `initial_data_reference` query-param),
 * valideert het eigenaarschap tegen de huidige user + tenant, en zet de
 * waarden klaar in een FormState.
 *
 * Bouwt verder op het patroon uit `ValidateOpenFormsPrefill` middleware maar
 * werkt op de Filament-page in plaats van op een request-middleware; de
 * validatie is inhoudelijk identiek.
 */
class PrefillLoader
{
    public function __construct(
        private readonly ObjectsApi $objectsApi,
    ) {}

    public function load(
        ?string $initialDataReference,
        User $user,
        Organisation $organisation,
    ): ?FormState {
        if ($initialDataReference === null || $initialDataReference === '') {
            return null;
        }

        /** @var array<string, mixed> $object */
        $object = $this->objectsApi->get($initialDataReference)->toArray();
        $data = Arr::get($object, 'record.data');

        if (! is_array($data)) {
            return null;
        }
        if (! isset($data['user_uuid'], $data['organiser_uuid'])) {
            return null;
        }
        if ($data['user_uuid'] !== $user->uuid) {
            return null;
        }
        if ($data['organiser_uuid'] !== $organisation->uuid) {
            return null;
        }

        $state = FormState::empty();
        $state->setVariable('prefill', $data);
        $state->setVariable('eventloketPrefill', $data);
        $state->setVariable('eventloketPrefillLoaded', true);

        return $state;
    }
}
