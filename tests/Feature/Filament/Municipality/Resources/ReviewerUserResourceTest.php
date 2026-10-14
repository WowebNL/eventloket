<?php

use App\Enums\Role;
use App\Filament\Municipality\Resources\ReviewerUserResource;
use App\Filament\Municipality\Resources\ReviewerUserResource\Pages\ListReviewerUsers;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

covers(ReviewerUserResource::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->member = function (Role $role): User {
        $user = User::factory()->create(['role' => $role]);

        $this->municipality->users()->attach($user);

        return $user;
    };
});

// --- Authorization on the reviewer user resource ---

test('a municipality admin can open the reviewer list page', function () {
    $this->actingAs(($this->member)(Role::MunicipalityAdmin));

    Filament::setTenant($this->municipality);

    livewire(ListReviewerUsers::class)->assertOk();
});

test('a reviewer municipality admin can open the reviewer list page', function () {
    $this->actingAs(($this->member)(Role::ReviewerMunicipalityAdmin));

    Filament::setTenant($this->municipality);

    livewire(ListReviewerUsers::class)->assertOk();
});

test('a reviewer of the same municipality is forbidden from the reviewer list page', function () {
    $this->actingAs(($this->member)(Role::Reviewer));

    Filament::setTenant($this->municipality);

    livewire(ListReviewerUsers::class)->assertForbidden();
});

test('a coordinator of the same municipality is forbidden from the reviewer list page', function () {
    $this->actingAs(($this->member)(Role::Coordinator));

    Filament::setTenant($this->municipality);

    livewire(ListReviewerUsers::class)->assertForbidden();
});

test('a municipality admin can still change a role through the inline role column', function () {
    $targetUser = ($this->member)(Role::Reviewer);

    $this->actingAs(($this->member)(Role::MunicipalityAdmin));

    Filament::setTenant($this->municipality);

    livewire(ListReviewerUsers::class)
        ->call('updateTableColumnState', 'role', $targetUser->getKey(), Role::Coordinator->value);

    expect($targetUser->refresh()->role)->toBe(Role::Coordinator);
});

test('a reviewer cannot change a role through the inline role column', function () {
    $targetUser = ($this->member)(Role::Reviewer);

    $this->actingAs(($this->member)(Role::Reviewer));

    Filament::setTenant($this->municipality);

    // The page itself is no longer reachable for this role, so the call may
    // never get to the column; assert on the stored role either way.
    rescue(function () use ($targetUser) {
        livewire(ListReviewerUsers::class)
            ->call('updateTableColumnState', 'role', $targetUser->getKey(), Role::ReviewerMunicipalityAdmin->value);
    }, report: false);

    expect($targetUser->refresh()->role)->toBe(Role::Reviewer);
});
