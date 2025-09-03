<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Organiser\Clusters\Settings\Resources\OrganiserUserResource\Widgets\PendingOrganisationInvitesWidget;
use App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages\ListOrganiserUsers;
use App\Livewire\AcceptInvites\AcceptOrganisationInvite;
use App\Mail\OrganisationInviteMail;
use App\Models\Organisation;
use App\Models\OrganisationInvite;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    Mail::fake();

    $this->organisation = Organisation::factory()->create([
        'name' => 'Test Organisation',
        'coc_number' => '12345678',
        'address' => '123 Test Street',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Organiser,
    ]);

    $this->organisation->users()->attach($this->admin, [
        'role' => OrganisationRole::Admin->value,
    ]);
});

test('organisation admin can create an invite', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'newuser@example.com';

    Filament::setTenant($this->organisation);

    // Act
    $response = livewire(ListOrganiserUsers::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
            'makeAdmin' => false,
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = OrganisationInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->organisation_id)->toBe($this->organisation->id)
        ->and($invite->role)->toBe(OrganisationRole::Member->value);

    Mail::assertSent(OrganisationInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('organisation admin can see and delete pending organisation invites', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'organiser@example.com';
    $invitee2Email = 'organiser2@example.com';

    $invite1 = OrganisationInvite::create([
        'organisation_id' => $this->organisation->id,
        'email' => $inviteeEmail,
        'role' => OrganisationRole::Member->value,
        'token' => Str::uuid(),
    ]);
    $invite2 = OrganisationInvite::create([
        'organisation_id' => $this->organisation->id,
        'email' => $invitee2Email,
        'role' => OrganisationRole::Member->value,
        'token' => Str::uuid(),
    ]);

    Filament::setTenant($this->organisation);

    // Act
    $listPage = livewire(ListOrganiserUsers::class)
        ->assertActionExists('pending-invites')
        ->assertActionEnabled('pending-invites')
        ->callAction('pending-invites');

    // Assert the action opens successfully
    $listPage->assertSuccessful();

    // Test the widget content directly
    $widget = livewire(PendingOrganisationInvitesWidget::class)
        ->assertCanSeeTableRecords([$invite1, $invite2])
        ->assertSee($inviteeEmail)
        ->assertSee($invitee2Email);

    // Test deleting a single record
    $widget->callTableAction('delete', $invite1->id)
        ->assertCanNotSeeTableRecords([$invite1])
        ->assertCanSeeTableRecords([$invite2]);

    // Verify the invite was actually deleted from the database
    expect(OrganisationInvite::find($invite1->id))->toBeNull()
        ->and(OrganisationInvite::find($invite2->id))->not->toBeNull();
});

test('existing user can accept an invite', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = OrganisationInvite::create([
        'organisation_id' => $this->organisation->id,
        'email' => $user->email,
        'role' => OrganisationRole::Member->value,
        'token' => Str::uuid(),
    ]);

    // Act
    $this->actingAs($user);
    $signedUrl = URL::signedRoute('organisation-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptOrganisationInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptOrganisationInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    $response->assertRedirect(route('filament.organiser.pages.dashboard', ['tenant' => $this->organisation->id]));

    $this->assertDatabaseHas('organisation_user', [
        'organisation_id' => $this->organisation->id,
        'user_id' => $user->id,
        'role' => OrganisationRole::Member->value,
    ]);

    $this->assertDatabaseMissing('organisation_invites', [
        'id' => $invite->id,
    ]);
});

test('new user can register and accept an invite', function () {
    // Arrange
    $inviteeEmail = 'newuser@example.com';
    $invite = OrganisationInvite::create([
        'organisation_id' => $this->organisation->id,
        'email' => $inviteeEmail,
        'role' => OrganisationRole::Member->value,
        'token' => Str::uuid(),
    ]);

    $signedUrl = URL::signedRoute('organisation-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptOrganisationInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptOrganisationInvite::class, ['token' => $invite->token])
        ->fillForm([
            'name' => 'New User',
            'phone' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create');

    // Assert
    $response->assertRedirect(route('filament.organiser.pages.dashboard', ['tenant' => $this->organisation->id]));

    $user = User::where('email', $inviteeEmail)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New User')
        ->and($user->phone)->toBe('1234567890');

    $this->assertDatabaseHas('organisation_user', [
        'organisation_id' => $this->organisation->id,
        'user_id' => $user->id,
        'role' => OrganisationRole::Member->value,
    ]);

    $this->assertDatabaseMissing('organisation_invites', [
        'id' => $invite->id,
    ]);
});

test('invite cannot be accepted by wrong user', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = OrganisationInvite::create([
        'organisation_id' => $this->organisation->id,
        'email' => 'differentuser@example.com', // Different email than logged-in user
        'role' => OrganisationRole::Member->value,
        'token' => Str::uuid(),
    ]);

    // Act
    $this->actingAs($user);
    $response = livewire(AcceptOrganisationInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    // Assert
    $response->assertStatus(403);

    $this->assertDatabaseMissing('organisation_user', [
        'organisation_id' => $this->organisation->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('organisation_invites', [
        'id' => $invite->id,
    ]);
});

test('admin can invite a user with admin role', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'newadmin@example.com';

    Filament::setTenant($this->organisation);

    // Act
    $response = livewire(ListOrganiserUsers::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
            'makeAdmin' => true,
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = OrganisationInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->organisation_id)->toBe($this->organisation->id)
        ->and($invite->role)->toBe(OrganisationRole::Admin->value);
});
