<?php

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->organisation = Organisation::factory()->create();

    $this->makeZaak = fn (array $attributes): Zaak => Zaak::withoutEvents(fn () => Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        ...$attributes,
    ]));

    $this->migration = include database_path('migrations/2026_06_25_000000_backfill_reviewer_user_id_on_zaken.php');
});

it('copies the handler into reviewer_user_id for handled cases', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->municipality);

    $zaak = ($this->makeZaak)([
        'handled_status_set_by_user_id' => $reviewer->id,
        'reviewer_user_id' => null,
    ]);

    $this->migration->up();

    expect($zaak->fresh()->reviewer_user_id)->toBe($reviewer->id);
});

it('leaves cases without a handler unassigned', function () {
    $zaak = ($this->makeZaak)([
        'handled_status_set_by_user_id' => null,
        'reviewer_user_id' => null,
    ]);

    $this->migration->up();

    expect($zaak->fresh()->reviewer_user_id)->toBeNull();
});

it('does not overwrite an already assigned reviewer', function () {
    $handler = User::factory()->create(['role' => Role::Reviewer]);
    $assignedReviewer = User::factory()->create(['role' => Role::Reviewer]);
    $handler->municipalities()->attach($this->municipality);
    $assignedReviewer->municipalities()->attach($this->municipality);

    $zaak = ($this->makeZaak)([
        'handled_status_set_by_user_id' => $handler->id,
        'reviewer_user_id' => $assignedReviewer->id,
    ]);

    $this->migration->up();

    expect($zaak->fresh()->reviewer_user_id)->toBe($assignedReviewer->id);
});

it('is idempotent when run more than once', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->municipality);

    $zaak = ($this->makeZaak)([
        'handled_status_set_by_user_id' => $reviewer->id,
        'reviewer_user_id' => null,
    ]);

    $this->migration->up();
    $this->migration->up();

    expect($zaak->fresh()->reviewer_user_id)->toBe($reviewer->id);
});
