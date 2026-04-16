<?php

declare(strict_types=1);

use App\Enums\Role;
use App\EventForm\Persistence\DraftStore;
use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->store = new DraftStore;
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);
});

test('load returns null when no draft exists for this user+organisation', function () {
    $result = $this->store->load($this->user, $this->organisation);

    expect($result)->toBeNull();
});

test('save persists a FormState that load can restore', function () {
    $state = FormState::empty();
    $state->setField('soortEvenement', 'Markt of braderie');
    $state->setVariable('gemeenteVariabelen', ['aanwezigen' => 500]);
    $state->setFieldHidden('locatieSOpKaart', false);
    $state->setStepApplicable('melding', false);

    $this->store->save($this->user, $this->organisation, $state, currentStepKey: 'stap-2-locatie');

    $loaded = $this->store->load($this->user, $this->organisation);

    expect($loaded)->not->toBeNull()
        ->and($loaded->get('soortEvenement'))->toBe('Markt of braderie')
        ->and($loaded->get('gemeenteVariabelen.aanwezigen'))->toBe(500)
        ->and($loaded->isFieldHidden('locatieSOpKaart'))->toBeFalse()
        ->and($loaded->isStepApplicable('melding'))->toBeFalse();
});

test('save stores the current step key so resume starts at the right place', function () {
    $state = FormState::empty();
    $this->store->save($this->user, $this->organisation, $state, currentStepKey: 'stap-9-kenmerken');

    expect($this->store->currentStepKey($this->user, $this->organisation))->toBe('stap-9-kenmerken');
});

test('save called twice for same user+organisation updates the existing draft', function () {
    $state1 = FormState::empty();
    $state1->setField('name', 'first');
    $this->store->save($this->user, $this->organisation, $state1, currentStepKey: 'stap-1');

    $state2 = FormState::empty();
    $state2->setField('name', 'second');
    $this->store->save($this->user, $this->organisation, $state2, currentStepKey: 'stap-2');

    $loaded = $this->store->load($this->user, $this->organisation);
    expect($loaded->get('name'))->toBe('second')
        ->and($this->store->currentStepKey($this->user, $this->organisation))->toBe('stap-2');
});

test('clear removes the draft', function () {
    $this->store->save($this->user, $this->organisation, FormState::empty(), currentStepKey: null);
    expect($this->store->load($this->user, $this->organisation))->not->toBeNull();

    $this->store->clear($this->user, $this->organisation);

    expect($this->store->load($this->user, $this->organisation))->toBeNull();
});

test('clear is a no-op when no draft exists', function () {
    expect(fn () => $this->store->clear($this->user, $this->organisation))->not->toThrow(Exception::class);
});

test('different organisations get separate drafts for the same user', function () {
    $orgB = Organisation::factory()->create();
    $this->user->organisations()->attach($orgB->id, ['role' => 'admin']);

    $stateA = FormState::empty();
    $stateA->setField('marker', 'A');
    $this->store->save($this->user, $this->organisation, $stateA, currentStepKey: null);

    $stateB = FormState::empty();
    $stateB->setField('marker', 'B');
    $this->store->save($this->user, $orgB, $stateB, currentStepKey: null);

    expect($this->store->load($this->user, $this->organisation)->get('marker'))->toBe('A')
        ->and($this->store->load($this->user, $orgB)->get('marker'))->toBe('B');
});

test('different users get separate drafts for the same organisation', function () {
    $userB = User::factory()->create(['role' => Role::Organiser]);
    $userB->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $stateA = FormState::empty();
    $stateA->setField('by', 'A');
    $this->store->save($this->user, $this->organisation, $stateA, currentStepKey: null);

    $stateB = FormState::empty();
    $stateB->setField('by', 'B');
    $this->store->save($userB, $this->organisation, $stateB, currentStepKey: null);

    expect($this->store->load($this->user, $this->organisation)->get('by'))->toBe('A')
        ->and($this->store->load($userB, $this->organisation)->get('by'))->toBe('B');
});
