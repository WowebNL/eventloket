<?php

use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Thread;
use App\Models\User;
use App\Models\Zaak;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create([
        'type' => OrganisationType::Business,
    ]);

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin->value]);

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->zaaktype = \App\Models\Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);
});

test('zaak handles soft-deleted organisation gracefully', function () {
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    // Verify zaak has organisation before soft delete
    expect($zaak->fresh()->organisation)->not->toBeNull()
        ->and($zaak->fresh()->organisation->id)->toBe($this->organisation->id);

    // Soft delete the organisation
    $this->organisation->delete();

    // Verify organisation is soft deleted
    $this->assertSoftDeleted($this->organisation);

    // Verify zaak still exists and can be accessed
    $zaak = $zaak->fresh();
    expect($zaak)->not->toBeNull();

    // Verify accessing organisation returns null (soft deleted)
    expect($zaak->organisation)->toBeNull();
});

test('zaak relatedUsers method handles soft-deleted organisation', function () {
    $municipalityUser = User::factory()->create(['role' => Role::Reviewer]);
    $this->zaaktype->municipality->users()->attach($municipalityUser);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    // Create an advice thread with advisory
    $advisory = \App\Models\Advisory::factory()->create();
    $advisoryUser = User::factory()->create(['role' => Role::Advisor]);
    $advisory->users()->attach($advisoryUser, ['role' => AdvisoryRole::Admin]);

    $adviceThread = Thread::factory()->create([
        'title' => 'Advice Thread',
        'zaak_id' => $zaak->id,
        'advisory_id' => $advisory->id,
        'type' => ThreadType::Advice,
    ]);

    // Before soft delete - should include organisation users
    $relatedUsers = $zaak->relatedUsers();
    expect(collect($relatedUsers)->pluck('id'))->toContain($this->organiser->id);

    // Soft delete the organisation
    $this->organisation->delete();

    // After soft delete - should not throw error
    $zaak = $zaak->fresh();
    expect(fn () => $zaak->relatedUsers())->not->toThrow(\Exception::class);

    // relatedUsers should still work but without organisation users
    $relatedUsersAfter = $zaak->relatedUsers();
    expect($relatedUsersAfter)->toBeArray()
        ->and(collect($relatedUsersAfter)->pluck('id'))->not->toContain($this->organiser->id);
});

test('zaak policy canAccessOrganisation handles soft-deleted organisation', function () {
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    // Before soft delete - user can access
    expect($this->organiser->canAccessOrganisation($this->organisation->id))->toBeTrue();

    // Soft delete the organisation
    $this->organisation->delete();

    // After soft delete - user cannot access
    expect($this->organiser->canAccessOrganisation($this->organisation->id))->toBeFalse();

    // Verify zaak policy respects this
    $policy = new \App\Policies\ZaakPolicy;
    expect($policy->view($this->organiser, $zaak))->toBeFalse();
});

test('organisation invite handles soft-deleted organisation', function () {
    $invite = \App\Models\OrganisationInvite::factory()->create([
        'organisation_id' => $this->organisation->id,
        'email' => 'test@example.com',
    ]);

    // Verify invite has organisation before soft delete
    expect($invite->fresh()->organisation)->not->toBeNull();

    // Soft delete the organisation
    $this->organisation->delete();

    // Verify invite still exists
    $invite = $invite->fresh();
    expect($invite)->not->toBeNull();

    // Verify accessing organisation returns null (soft deleted)
    expect($invite->organisation)->toBeNull();
});

test('organisation invite mail handles soft-deleted organisation', function () {
    $invite = \App\Models\OrganisationInvite::factory()->create([
        'organisation_id' => $this->organisation->id,
        'email' => 'test@example.com',
    ]);

    // Soft delete the organisation
    $this->organisation->delete();

    // Refresh invite to get fresh data
    $invite = $invite->fresh();

    // Verify that creating mail with soft-deleted organisation doesn't throw error
    expect(fn () => new \App\Mail\OrganisationInviteMail($invite))
        ->not->toThrow(\Exception::class);
});

test('accept organisation invite handles soft-deleted organisation gracefully', function () {
    $invite = \App\Models\OrganisationInvite::factory()->create([
        'organisation_id' => $this->organisation->id,
        'email' => 'newuser@example.com',
    ]);

    // Soft delete the organisation
    $this->organisation->delete();

    // Attempt to accept invite with soft-deleted organisation
    $newUser = User::factory()->create([
        'email' => 'newuser@example.com',
        'role' => Role::Organiser,
    ]);

    $this->actingAs($newUser);

    // Try to access the accept invite livewire component
    $component = \Livewire\Livewire::test(\App\Livewire\AcceptInvites\AcceptOrganisationInvite::class, [
        'token' => $invite->token,
    ]);

    // Should handle soft-deleted organisation gracefully (show error or redirect)
    // The exact behavior depends on your implementation
    expect($component)->not->toThrow(\Exception::class);
});

test('organisation users relationship excludes soft-deleted organisations', function () {
    $otherOrganisation = Organisation::factory()->create([
        'type' => OrganisationType::Business,
    ]);

    // Attach user to both organisations
    //    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Member->value]);
    $otherOrganisation->users()->attach($this->organiser, ['role' => OrganisationRole::Member->value]);

    // Before soft delete - user has 2 organisations
    expect($this->organiser->organisations()->count())->toBe(2);

    // Soft delete one organisation
    $this->organisation->delete();

    // After soft delete - user has only 1 organisation (without withTrashed)
    expect($this->organiser->organisations()->count())->toBe(1)
        ->and($this->organiser->organisations()->first()->id)->toBe($otherOrganisation->id);

    // With withTrashed - user still has 2 organisations
    expect($this->organiser->organisations()->withTrashed()->count())->toBe(2);
});

test('calendar widget organisations filter handles soft-deleted organisations', function () {
    $otherOrganisation = Organisation::factory()->create([
        'type' => OrganisationType::Business,
    ]);

    // Attach user to both organisations
    //    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Member->value]);
    $otherOrganisation->users()->attach($this->organiser, ['role' => OrganisationRole::Member->value]);

    // Soft delete one organisation
    $this->organisation->delete();

    // Verify user's organisations don't include soft-deleted by default
    $organisations = $this->organiser->organisations;
    expect($organisations->count())->toBe(1)
        ->and($organisations->pluck('id')->contains($this->organisation->id))->toBeFalse()
        ->and($organisations->pluck('id')->contains($otherOrganisation->id))->toBeTrue();
});

test('zaak with soft-deleted organisation can still be viewed by municipality users', function () {
    $municipalityUser = User::factory()->create(['role' => Role::Reviewer]);
    $this->zaaktype->municipality->users()->attach($municipalityUser);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    // Soft delete the organisation
    $this->organisation->delete();

    // Municipality user should still be able to view zaak
    $policy = new \App\Policies\ZaakPolicy;
    expect($policy->view($municipalityUser, $zaak))->toBeTrue();
});

test('organisation restore works correctly', function () {
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    // Soft delete the organisation
    $this->organisation->delete();
    $this->assertSoftDeleted($this->organisation);

    // Verify zaak cannot access organisation
    expect($zaak->fresh()->organisation)->toBeNull();

    // Restore the organisation
    $this->organisation->restore();

    // Verify organisation is restored
    expect($this->organisation->fresh()->deleted_at)->toBeNull();

    // Verify zaak can access organisation again
    expect($zaak->fresh()->organisation)->not->toBeNull()
        ->and($zaak->fresh()->organisation->id)->toBe($this->organisation->id);
});

test('form submission object handles soft-deleted organisation by uuid', function () {
    // This tests the CreateZaak job scenario where organisation is looked up by UUID
    $organisationUuid = $this->organisation->uuid;

    // Soft delete the organisation
    $this->organisation->delete();

    // Try to find organisation by UUID (should return null without withTrashed)
    $foundOrganisation = Organisation::where('uuid', $organisationUuid)->first();
    expect($foundOrganisation)->toBeNull();

    // With withTrashed - should find it
    $foundOrganisationWithTrashed = Organisation::withTrashed()->where('uuid', $organisationUuid)->first();
    expect($foundOrganisationWithTrashed)->not->toBeNull()
        ->and($foundOrganisationWithTrashed->id)->toBe($this->organisation->id);
});

test('user can still access their other organisations after one is soft deleted', function () {
    $organisation1 = Organisation::factory()->create(['type' => OrganisationType::Business]);
    $organisation2 = Organisation::factory()->create(['type' => OrganisationType::Business]);
    $organisation3 = Organisation::factory()->create(['type' => OrganisationType::Business]);

    $user = User::factory()->create(['role' => Role::Organiser]);

    // Attach user to all three organisations
    $organisation1->users()->attach($user, ['role' => OrganisationRole::Member->value]);
    $organisation2->users()->attach($user, ['role' => OrganisationRole::Member->value]);
    $organisation3->users()->attach($user, ['role' => OrganisationRole::Member->value]);

    expect($user->organisations()->count())->toBe(3);

    // Soft delete organisation2
    $organisation2->delete();

    // User should still have access to organisation1 and organisation3
    $userOrganisations = $user->fresh()->organisations;
    expect($userOrganisations->count())->toBe(2)
        ->and($userOrganisations->pluck('id')->contains($organisation1->id))->toBeTrue()
        ->and($userOrganisations->pluck('id')->contains($organisation2->id))->toBeFalse()
        ->and($userOrganisations->pluck('id')->contains($organisation3->id))->toBeTrue();
});
