<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\ListMunicipalityAdminUsers;
use App\Filament\Municipality\Resources\ReviewerUserResource\Pages\ListReviewerUsers;
use App\Livewire\AcceptInvites\AcceptMunicipalityInvite;
use App\Mail\MunicipalityInviteMail;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    Mail::fake();

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->municipalityAdmin = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($this->municipalityAdmin);
});

test('municipality admin can create a reviewer invite', function () {
    // Arrange
    $this->actingAs($this->municipalityAdmin);
    $inviteeEmail = 'reviewer@example.com';

    Filament::setTenant($this->municipality);

    // Act
    $response = livewire(ListReviewerUsers::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = MunicipalityInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->municipalities()->first()->id)->toBe($this->municipality->id)
        ->and($invite->role)->toBe(Role::Reviewer);

    Mail::assertSent(MunicipalityInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('reviewer municipality admin can create a reviewer invite', function () {
    // Arrange
    $reviewerMunicipalityAdmin = User::factory()->create([
        'email' => 'reviewer-municipality-admin@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);

    $this->actingAs($reviewerMunicipalityAdmin);
    $inviteeEmail = 'reviewer@example.com';

    Filament::setTenant($this->municipality);

    // Act
    $response = livewire(ListReviewerUsers::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = MunicipalityInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->municipalities()->first()->id)->toBe($this->municipality->id)
        ->and($invite->role)->toBe(Role::Reviewer);

    Mail::assertSent(MunicipalityInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('existing user can accept a reviewer invite', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = MunicipalityInvite::create([
        'email' => $user->email,
        'role' => Role::Reviewer,
        'token' => Str::uuid(),
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    // Act
    $this->actingAs($user);
    $signedUrl = URL::signedRoute('municipality-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptMunicipalityInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptMunicipalityInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    $response->assertRedirect(route('filament.municipality.pages.dashboard', ['tenant' => $this->municipality->id]));

    $this->assertDatabaseHas('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('municipality_invites', [
        'id' => $invite->id,
    ]);
});

test('new user can register and accept a reviewer invite', function () {
    // Arrange
    $inviteeEmail = 'newreviewer@example.com';
    $invite = MunicipalityInvite::create([
        'email' => $inviteeEmail,
        'role' => Role::Reviewer,
        'token' => Str::uuid(),
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    $signedUrl = URL::signedRoute('municipality-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptMunicipalityInvite::class);

    // Test the registration and accept invite action
    $response = livewire(AcceptMunicipalityInvite::class, ['token' => $invite->token])
        ->fillForm([
            'name' => 'New Reviewer',
            'phone' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create');

    // Assert
    $response->assertRedirect(route('filament.municipality.pages.dashboard', ['tenant' => $this->municipality->id]));

    $user = User::where('email', $inviteeEmail)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Reviewer')
        ->and($user->phone)->toBe('1234567890')
        ->and($user->role)->toBe(Role::Reviewer);

    $this->assertDatabaseHas('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('municipality_invites', [
        'id' => $invite->id,
    ]);
});

test('municipality admin can create a municipality admin invite', function () {
    // Arrange
    $this->actingAs($this->municipalityAdmin);
    $inviteeEmail = 'another-municipalityadmin@example.com';

    Filament::setTenant($this->municipality);

    // Act - Assuming you have a page to invite municipality admins
    $response = livewire(ListMunicipalityAdminUsers::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
            'role' => Role::MunicipalityAdmin->value,
            'municipalities' => [$this->municipality->id],
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = MunicipalityInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->municipalities()->first()->id)->toBe($this->municipality->id)
        ->and($invite->role)->toBe(Role::MunicipalityAdmin);

    Mail::assertSent(MunicipalityInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('invite cannot be accepted by wrong user', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = MunicipalityInvite::create([
        'email' => 'differentuser@example.com', // Different email than logged-in user
        'token' => Str::uuid(),
        'role' => Role::Reviewer,
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    // Act
    $this->actingAs($user);
    $response = livewire(AcceptMunicipalityInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    // Assert
    $response->assertStatus(403);

    $this->assertDatabaseMissing('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('municipality_invites', [
        'id' => $invite->id,
    ]);
});

// TODO Lorenso
// test('reviewer cannot create municipality admin invites', function () {
//    // Arrange
//    $reviewer = User::factory()->create([
//        'email' => 'reviewer@example.com',
//        'role' => Role::Reviewer,
//    ]);
//    $this->municipality->users()->attach($reviewer);
//
//    $this->actingAs($reviewer);
//    $inviteeEmail = 'new-municadmin@example.com';
//
//    Filament::setTenant($this->municipality);
//
//    // Act & Assert - should not have permission
//    livewire(ListMunicipalityAdminUsers::class)
//        ->callAction('invite', [
//            'email' => $inviteeEmail,
//            'role' => Role::MunicipalityAdmin->value,
//            'municipalities' => [$this->municipality->id],
//        ])
//        ->assertForbidden();
// });
