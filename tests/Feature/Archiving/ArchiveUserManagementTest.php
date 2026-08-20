<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Pages\ListArchiveUsers;
use App\Filament\Municipality\Resources\ReviewerUserResource\Pages\ListReviewerUsers;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Users\ArchiveCoordinatorUser;
use App\Models\Users\ReviewerUser;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create();

    $this->municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($this->municipalityAdmin);

    $this->actingAs($this->municipalityAdmin);

    Filament::setTenant($this->municipality);
    Filament::bootCurrentPanel();
});

test('a municipality admin can assign an archive role to an existing reviewer', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);

    $component = livewire(ListReviewerUsers::class)
        ->assertCanSeeTableRecords([$reviewer]);

    $component->call('updateTableColumnState', 'role', $reviewer->getKey(), Role::ArchiveCoordinator->value);

    $reviewer->refresh();

    expect($reviewer->role)->toBe(Role::ArchiveCoordinator)
        ->and(User::find($reviewer->id))->toBeInstanceOf(ArchiveCoordinatorUser::class);

    // The user now shows up in the archive users list instead of the reviewer list
    livewire(ListArchiveUsers::class)
        ->assertCanSeeTableRecords([$reviewer]);

    livewire(ListReviewerUsers::class)
        ->assertCanNotSeeTableRecords([$reviewer]);
});

test('a municipality admin can switch an archive user back to a regular role', function () {
    $archiveUser = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($archiveUser);

    $component = livewire(ListArchiveUsers::class)
        ->assertCanSeeTableRecords([$archiveUser]);

    $component->call('updateTableColumnState', 'role', $archiveUser->getKey(), Role::Reviewer->value);

    $archiveUser->refresh();

    expect($archiveUser->role)->toBe(Role::Reviewer)
        ->and(User::find($archiveUser->id))->toBeInstanceOf(ReviewerUser::class);

    livewire(ListReviewerUsers::class)
        ->assertCanSeeTableRecords([$archiveUser]);
});
