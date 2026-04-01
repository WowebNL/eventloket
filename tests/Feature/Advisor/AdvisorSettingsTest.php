<?php

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Filament\Advisor\Clusters\Settings\Resources\AdvisorUsers\AdvisorUserResource;
use App\Filament\Advisor\Clusters\Settings\Resources\AdvisorUsers\Pages\ListAdvisorUsers;
use App\Filament\Advisor\Clusters\Settings\SettingsCluster;
use App\Filament\Shared\Resources\AdvisorUsers\Actions\AdvisorUserInviteAction;
use App\Filament\Shared\Resources\AdvisorUsers\Widgets\PendingAdvisoryInvitesWidget;
use App\Mail\AdvisoryInviteMail;
use App\Models\Advisory;
use App\Models\AdvisoryInvite;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

covers(SettingsCluster::class, AdvisorUserResource::class, AdvisorUserInviteAction::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));

    Mail::fake();

    $this->advisory = Advisory::factory()->create(['name' => 'Test Advisory']);

    $this->adminAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->adminAdvisor, ['role' => AdvisoryRole::Admin]);

    $this->memberAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->memberAdvisor, ['role' => AdvisoryRole::Member]);
});

// --- SettingsCluster access control ---

test('advisory admin can access settings cluster', function () {
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    expect(SettingsCluster::canAccess())->toBeTrue();
});

test('advisory member cannot access settings cluster', function () {
    $this->actingAs($this->memberAdvisor);
    Filament::setTenant($this->advisory);

    expect(SettingsCluster::canAccess())->toBeFalse();
});

// --- AdvisorUserResource access control ---

test('advisory admin can access the advisor users list page', function () {
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->assertOk();
});

test('advisory member is forbidden from the advisor users list page', function () {
    $this->actingAs($this->memberAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->assertForbidden();
});

test('advisor users list shows existing advisory members', function () {
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->assertCanSeeTableRecords([$this->adminAdvisor, $this->memberAdvisor]);
});

// --- AdvisorUserInviteAction ---

test('advisory admin can invite a new advisor', function () {
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableAction('invite', null, [
            'name' => 'New Advisor',
            'email' => 'newadvisor@example.com',
            'makeAdmin' => false,
        ])
        ->assertHasNoTableActionErrors();

    expect(AdvisoryInvite::where('email', 'newadvisor@example.com')->where('advisory_id', $this->advisory->id)->exists())
        ->toBeTrue();
});

test('invite action sends an email to the invitee', function () {
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableAction('invite', null, [
            'name' => 'New Advisor',
            'email' => 'invitee@example.com',
            'makeAdmin' => false,
        ]);

    Mail::assertSent(AdvisoryInviteMail::class, fn ($mail) => $mail->hasTo('invitee@example.com'));
});

test('invite action with makeAdmin creates an admin role invite', function () {
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableAction('invite', null, [
            'name' => 'Admin Advisor',
            'email' => 'adminadvisor@example.com',
            'makeAdmin' => true,
        ]);

    $invite = AdvisoryInvite::where('email', 'adminadvisor@example.com')->first();
    expect($invite)->not->toBeNull()
        ->and($invite->role)->toBe(AdvisoryRole::Admin->value);
});

test('invite action with makeAdmin false creates a member role invite', function () {
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableAction('invite', null, [
            'name' => 'Member Advisor',
            'email' => 'memberadvisor@example.com',
            'makeAdmin' => false,
        ]);

    $invite = AdvisoryInvite::where('email', 'memberadvisor@example.com')->first();
    expect($invite)->not->toBeNull()
        ->and($invite->role)->toBe(AdvisoryRole::Member->value);
});

test('invite action rejects a duplicate email for the same advisory', function () {
    AdvisoryInvite::create([
        'advisory_id' => $this->advisory->id,
        'email' => 'duplicate@example.com',
        'role' => AdvisoryRole::Member,
        'token' => Str::uuid(),
    ]);

    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableAction('invite', null, [
            'name' => 'Duplicate',
            'email' => 'duplicate@example.com',
            'makeAdmin' => false,
        ])
        ->assertHasTableActionErrors(['email']);
});

// --- PendingAdvisoryInvitesWidget ---

test('pending invites widget shows outstanding invites for the advisory', function () {
    $invite = AdvisoryInvite::create([
        'advisory_id' => $this->advisory->id,
        'email' => 'pending@example.com',
        'role' => AdvisoryRole::Member,
        'token' => Str::uuid(),
    ]);

    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(PendingAdvisoryInvitesWidget::class, ['record' => $this->advisory])
        ->assertCanSeeTableRecords([$invite]);
});

test('pending invites widget does not show invites from other advisories', function () {
    $otherAdvisory = Advisory::factory()->create(['name' => 'Other Advisory']);

    $ownInvite = AdvisoryInvite::create([
        'advisory_id' => $this->advisory->id,
        'email' => 'own@example.com',
        'role' => AdvisoryRole::Member,
        'token' => Str::uuid(),
    ]);

    $otherInvite = AdvisoryInvite::create([
        'advisory_id' => $otherAdvisory->id,
        'email' => 'other@example.com',
        'role' => AdvisoryRole::Member,
        'token' => Str::uuid(),
    ]);

    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(PendingAdvisoryInvitesWidget::class, ['record' => $this->advisory])
        ->assertCanSeeTableRecords([$ownInvite])
        ->assertCanNotSeeTableRecords([$otherInvite]);
});
