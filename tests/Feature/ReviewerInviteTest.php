<?php

use App\Enums\Role;
use App\Filament\Pages\AcceptReviewerInvite;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Mail\ReviewerInviteMail;
use App\Models\Municipality;
use App\Models\ReviewerInvite;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Mail::fake();

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $this->municipality->users()->attach($this->admin);
});

test('admin can create a reviewer invite', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'reviewer@example.com';

    Filament::setTenant($this->municipality);

    // Act
    $response = livewire(ListUsers::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = ReviewerInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->municipality_id)->toBe($this->municipality->id);

    Mail::assertSent(ReviewerInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('existing user can accept a reviewer invite', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = ReviewerInvite::create([
        'municipality_id' => $this->municipality->id,
        'email' => $user->email,
        'token' => Str::uuid(),
    ]);

    // Act
    $this->actingAs($user);
    $signedUrl = URL::signedRoute('reviewer-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptReviewerInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptReviewerInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    $response->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality->id]));

    $this->assertDatabaseHas('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('reviewer_invites', [
        'id' => $invite->id,
    ]);
});

test('new user can register and accept a reviewer invite', function () {
    // Arrange
    $inviteeEmail = 'newreviewer@example.com';
    $invite = ReviewerInvite::create([
        'municipality_id' => $this->municipality->id,
        'email' => $inviteeEmail,
        'token' => Str::uuid(),
    ]);

    $signedUrl = URL::signedRoute('reviewer-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptReviewerInvite::class);

    // Test the registration and accept invite action
    $response = livewire(AcceptReviewerInvite::class, ['token' => $invite->token])
        ->fillForm([
            'name' => 'New Reviewer',
            'phone' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create');

    // Assert
    $response->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality->id]));

    $user = User::where('email', $inviteeEmail)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Reviewer')
        ->and($user->phone)->toBe('1234567890')
        ->and($user->role)->toBe(Role::Reviewer);

    $this->assertDatabaseHas('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('reviewer_invites', [
        'id' => $invite->id,
    ]);
});

test('reviewer invite cannot be accepted by wrong user', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = ReviewerInvite::create([
        'municipality_id' => $this->municipality->id,
        'email' => 'differentuser@example.com', // Different email than logged-in user
        'token' => Str::uuid(),
    ]);

    // Act
    $this->actingAs($user);
    $response = livewire(AcceptReviewerInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    // Assert
    $response->assertStatus(403);

    $this->assertDatabaseMissing('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('reviewer_invites', [
        'id' => $invite->id,
    ]);
});
