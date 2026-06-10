<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\TableState;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($this->admin);

    $this->sortSessionKey = (new ListUsers)->getTableSortSessionKey();
    $this->filtersSessionKey = (new ListUsers)->getTableFiltersSessionKey();
});

test('sorting a table persists the state to the database for the user', function () {
    livewire(ListUsers::class)
        ->sortTable('email', 'desc');

    $state = TableState::query()
        ->where('user_id', $this->admin->id)
        ->where('table_key', ListUsers::class)
        ->first();

    expect($state)->not->toBeNull()
        ->and($state->state)->toHaveKey($this->sortSessionKey, 'email:desc');
});

test('table state is restored from the database into a fresh session', function () {
    TableState::factory()->create([
        'user_id' => $this->admin->id,
        'table_key' => ListUsers::class,
        'state' => [
            $this->sortSessionKey => 'email:desc',
        ],
    ]);

    expect(session()->has($this->sortSessionKey))->toBeFalse();

    livewire(ListUsers::class)
        ->assertSet('tableSort', 'email:desc');

    expect(session($this->sortSessionKey))->toBe('email:desc');
});

test('seeding from the database does not overwrite an existing session value', function () {
    session()->put($this->sortSessionKey, 'role:asc');

    TableState::factory()->create([
        'user_id' => $this->admin->id,
        'table_key' => ListUsers::class,
        'state' => [
            $this->sortSessionKey => 'email:desc',
        ],
    ]);

    livewire(ListUsers::class)
        ->assertSet('tableSort', 'role:asc');

    expect(session($this->sortSessionKey))->toBe('role:asc');
});

test('persisting new table state is merged with previously stored state', function () {
    livewire(ListUsers::class)
        ->sortTable('email', 'desc');

    livewire(ListUsers::class)
        ->filterTable('trashed', true);

    $state = TableState::query()
        ->where('user_id', $this->admin->id)
        ->where('table_key', ListUsers::class)
        ->first();

    expect($state->state)
        ->toHaveKey($this->sortSessionKey, 'email:desc')
        ->toHaveKey($this->filtersSessionKey);
});
