<?php

use App\Enums\Role;
use App\Filament\Resources\AdvisoryResource\Pages\EditAdvisory;
use App\Filament\Resources\AdvisoryResource\RelationManagers\UsersRelationManager;
use App\Livewire\AcceptInvites\AcceptAdvisoryInvite;
use App\Mail\AdvisoryInviteMail;
use App\Models\Advisory;
use App\Models\AdvisoryInvite;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Mail::fake();

    $this->municipality = Municipality::factory()->create();

    $this->advisory = Advisory::factory()->create([
        'name' => 'Test Advisory',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);
});

test('admin can create an advisory invite', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'advisor@example.com';

    Filament::setTenant($this->municipality);

    // Act
    $response = livewire(UsersRelationManager::class, [
        'ownerRecord' => $this->advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->callTableAction('invite', null, [
            'email' => $inviteeEmail,
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = AdvisoryInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->advisory_id)->toBe($this->advisory->id);

    Mail::assertSent(AdvisoryInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('existing user can accept an advisory invite', function () {
    // Arrange
    Filament::setCurrentPanel(Filament::getPanel('advisor'));

    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
        'role' => Role::Advisor,
    ]);

    $invite = AdvisoryInvite::create([
        'advisory_id' => $this->advisory->id,
        'email' => $user->email,
        'token' => Str::uuid(),
    ]);

    // Act
    $this->actingAs($user);
    $signedUrl = URL::signedRoute('advisory-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdvisoryInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptAdvisoryInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    $response->assertRedirect(route('filament.advisor.pages.dashboard', ['tenant' => $this->advisory->id]));

    $this->assertDatabaseHas('advisory_user', [
        'advisory_id' => $this->advisory->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('advisory_invites', [
        'id' => $invite->id,
    ]);
});

test('new user can register and accept an advisory invite', function () {
    // Arrange
    Filament::setCurrentPanel(Filament::getPanel('advisor'));

    $inviteeEmail = 'newadvisor@example.com';
    $invite = AdvisoryInvite::create([
        'advisory_id' => $this->advisory->id,
        'email' => $inviteeEmail,
        'token' => Str::uuid(),
    ]);

    $signedUrl = URL::signedRoute('advisory-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdvisoryInvite::class);

    // Test the registration and accept invite action
    $response = livewire(AcceptAdvisoryInvite::class, ['token' => $invite->token])
        ->fillForm([
            'name' => 'New Advisor',
            'phone' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create');

    // Assert
    $response->assertRedirect(route('filament.advisor.pages.dashboard', ['tenant' => $this->advisory->id]));

    $user = User::where('email', $inviteeEmail)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Advisor')
        ->and($user->phone)->toBe('1234567890')
        ->and($user->role)->toBe(Role::Advisor);

    $this->assertDatabaseHas('advisory_user', [
        'advisory_id' => $this->advisory->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('advisory_invites', [
        'id' => $invite->id,
    ]);
});

test('advisory invite cannot be accepted by wrong user', function () {
    // Arrange
    Filament::setCurrentPanel(Filament::getPanel('advisor'));

    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
        'role' => Role::Advisor,
    ]);

    $invite = AdvisoryInvite::create([
        'advisory_id' => $this->advisory->id,
        'email' => 'differentuser@example.com', // Different email than logged-in user
        'token' => Str::uuid(),
    ]);

    // Act
    $this->actingAs($user);
    $response = livewire(AcceptAdvisoryInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    // Assert
    $response->assertStatus(403);

    $this->assertDatabaseMissing('advisory_user', [
        'advisory_id' => $this->advisory->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('advisory_invites', [
        'id' => $invite->id,
    ]);
});
