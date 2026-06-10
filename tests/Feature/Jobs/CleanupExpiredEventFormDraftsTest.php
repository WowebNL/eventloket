<?php

declare(strict_types=1);

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\DraftStore;
use App\Jobs\CleanupExpiredEventFormDrafts;
use App\Models\Organisation;
use App\Models\User;

test('de job verwijdert verlopen concepten en laat recente staan', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $organisation = Organisation::factory()->create();
    $user->organisations()->attach($organisation->id, ['role' => 'admin']);

    $expired = Draft::create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'state' => ['values' => [], 'system' => []],
    ]);
    Draft::whereKey($expired->id)->update([
        'updated_at' => now()->subMonths(DraftStore::EXPIRY_MONTHS)->subDay(),
    ]);

    $recent = Draft::create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'state' => ['values' => ['marker' => 'recent'], 'system' => []],
    ]);

    (new CleanupExpiredEventFormDrafts)->handle(new DraftStore);

    expect(Draft::whereKey($expired->id)->exists())->toBeFalse()
        ->and(Draft::whereKey($recent->id)->exists())->toBeTrue();
});
