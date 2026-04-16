<?php

declare(strict_types=1);

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\State\FormState;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->actingAs($this->user);
    \Filament\Facades\Filament::setTenant($this->organisation);
});

test('the page mounts for an authenticated user', function () {
    Livewire::test(EventFormPage::class)
        ->assertOk();
});

test('mount seeds FormState with authUser and authOrganisation', function () {
    $component = Livewire::test(EventFormPage::class);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state())->toBeInstanceOf(FormState::class)
        ->and($page->state()->get('authUser'))->toBeInstanceOf(User::class)
        ->and($page->state()->get('authOrganisation'))->toBeInstanceOf(Organisation::class);
});

test('mount hydrates eventloketSession via ServiceFetcher', function () {
    $component = Livewire::test(EventFormPage::class);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state()->get('eventloketSession.user_uuid'))->toBe($this->user->uuid)
        ->and($page->state()->get('eventloketSession.organiser_uuid'))->toBe($this->organisation->uuid);
});

test('mount loads an existing draft if present for user + organisation', function () {
    $state = FormState::empty();
    $state->setField('watIsUwVoornaam', 'Eva');
    Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => $state->toSnapshot(),
        'current_step_key' => null,
    ]);

    $component = Livewire::test(EventFormPage::class);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state()->get('watIsUwVoornaam'))->toBe('Eva');
});
