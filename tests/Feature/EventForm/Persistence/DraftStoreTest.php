<?php

declare(strict_types=1);

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\DraftLimitReached;
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

test('listFor returns no drafts when none exist for this user+organisation', function () {
    expect($this->store->listFor($this->user, $this->organisation))->toBeEmpty();
});

test('create + save persist a FormState that can be restored via findFor', function () {
    $state = FormState::empty();
    $state->setField('soortEvenement', 'Markt of braderie');
    $state->setVariable('gemeenteVariabelen', ['aanwezigen' => 500]);

    $draft = $this->store->create($this->user, $this->organisation, FormState::empty());
    $this->store->save($draft, $state, currentStepKey: 'stap-2-locatie');

    $found = $this->store->findFor($this->user, $this->organisation, $draft->id);
    $loaded = FormState::fromSnapshot($found->state ?? []);

    expect($found)->not->toBeNull()
        ->and($loaded->get('soortEvenement'))->toBe('Markt of braderie')
        ->and($loaded->get('gemeenteVariabelen.aanwezigen'))->toBe(500)
        ->and($found->current_step_key)->toBe('stap-2-locatie');
});

test('save derives the draft name from the event name field', function () {
    $draft = $this->store->create($this->user, $this->organisation, FormState::empty());

    $state = FormState::empty();
    $state->setField('watIsDeNaamVanHetEvenementVergunning', 'Buurtfeest Testlaan');
    $this->store->save($draft, $state, currentStepKey: null);

    expect($draft->refresh()->name)->toBe('Buurtfeest Testlaan')
        ->and($draft->display_name)->toBe('Buurtfeest Testlaan');
});

test('display_name falls back to creation date when the event name is empty', function () {
    $draft = $this->store->create($this->user, $this->organisation, FormState::empty());

    expect($draft->name)->toBeNull()
        ->and($draft->display_name)->toBe('Concept van '.$draft->created_at->format('d-m-Y'));
});

test('a user can have multiple drafts for the same organisation', function () {
    $first = $this->store->create($this->user, $this->organisation, FormState::empty());
    $stateA = FormState::empty();
    $stateA->setField('marker', 'A');
    $this->store->save($first, $stateA, currentStepKey: null);

    $second = $this->store->create($this->user, $this->organisation, FormState::empty());
    $stateB = FormState::empty();
    $stateB->setField('marker', 'B');
    $this->store->save($second, $stateB, currentStepKey: null);

    expect($first->id)->not->toBe($second->id)
        ->and($this->store->listFor($this->user, $this->organisation))->toHaveCount(2);
});

test('create reuses an existing empty draft instead of stacking junk rows', function () {
    $first = $this->store->create($this->user, $this->organisation, FormState::empty());
    $second = $this->store->create($this->user, $this->organisation, FormState::empty());

    expect($second->id)->toBe($first->id)
        ->and(Draft::count())->toBe(1);
});

test('create with a prefilled state always makes a new row', function () {
    $empty = $this->store->create($this->user, $this->organisation, FormState::empty());

    $prefill = FormState::empty();
    $prefill->setField('watIsDeNaamVanHetEvenementVergunning', 'Hergebruikte aanvraag');
    $prefilled = $this->store->create($this->user, $this->organisation, $prefill);

    expect($prefilled->id)->not->toBe($empty->id)
        ->and($prefilled->name)->toBe('Hergebruikte aanvraag');
});

test('create throws DraftLimitReached when the cap is hit', function () {
    foreach (range(1, DraftStore::MAX_DRAFTS) as $i) {
        $state = FormState::empty();
        $state->setField('marker', "concept-{$i}");
        Draft::create([
            'user_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'state' => $state->toSnapshot(),
        ]);
    }

    expect($this->store->hasCapacity($this->user, $this->organisation))->toBeFalse()
        ->and(fn () => $this->store->create($this->user, $this->organisation, FormState::empty()))
        ->toThrow(DraftLimitReached::class);
});

test('saveStep only updates the current step key', function () {
    $state = FormState::empty();
    $state->setField('marker', 'blijft');
    $draft = $this->store->create($this->user, $this->organisation, FormState::empty());
    $this->store->save($draft, $state, currentStepKey: 'stap-1');

    $this->store->saveStep($draft, 'stap-9-kenmerken');

    $draft->refresh();
    expect($draft->current_step_key)->toBe('stap-9-kenmerken')
        ->and(FormState::fromSnapshot($draft->state)->get('marker'))->toBe('blijft');
});

test('delete removes the draft', function () {
    $draft = $this->store->create($this->user, $this->organisation, FormState::empty());

    $this->store->delete($draft);

    expect($this->store->findFor($this->user, $this->organisation, $draft->id))->toBeNull();
});

test('findFor scopes ownership: drafts of other users or organisations stay hidden', function () {
    $orgB = Organisation::factory()->create();
    $this->user->organisations()->attach($orgB->id, ['role' => 'admin']);
    $userB = User::factory()->create(['role' => Role::Organiser]);
    $userB->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $own = $this->store->create($this->user, $this->organisation, FormState::empty());
    $otherOrg = $this->store->create($this->user, $orgB, FormState::empty());
    $otherUser = $this->store->create($userB, $this->organisation, FormState::empty());

    expect($this->store->findFor($this->user, $this->organisation, $own->id))->not->toBeNull()
        ->and($this->store->findFor($this->user, $this->organisation, $otherOrg->id))->toBeNull()
        ->and($this->store->findFor($this->user, $this->organisation, $otherUser->id))->toBeNull()
        ->and($this->store->listFor($this->user, $this->organisation)->pluck('id')->all())->toBe([$own->id]);
});

test('pruneExpired removes drafts untouched for the expiry window, keeps recent ones', function () {
    $expired = $this->store->create($this->user, $this->organisation, FormState::empty());
    Draft::whereKey($expired->id)->update([
        'updated_at' => now()->subMonths(DraftStore::EXPIRY_MONTHS)->subDay(),
    ]);

    $state = FormState::empty();
    $state->setField('marker', 'recent');
    $recent = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => $state->toSnapshot(),
    ]);
    // Grensgeval: exact op de rand (niet ouder dan) blijft staan.
    Draft::whereKey($recent->id)->update([
        'updated_at' => now()->subMonths(DraftStore::EXPIRY_MONTHS)->addHour(),
    ]);

    $deleted = $this->store->pruneExpired();

    expect($deleted)->toBe(1)
        ->and(Draft::whereKey($expired->id)->exists())->toBeFalse()
        ->and(Draft::whereKey($recent->id)->exists())->toBeTrue();
});

test('listFor prunes expired drafts before listing', function () {
    $expired = $this->store->create($this->user, $this->organisation, FormState::empty());
    Draft::whereKey($expired->id)->update([
        'updated_at' => now()->subMonths(DraftStore::EXPIRY_MONTHS)->subDay(),
    ]);

    expect($this->store->listFor($this->user, $this->organisation))->toBeEmpty()
        ->and(Draft::whereKey($expired->id)->exists())->toBeFalse();
});
