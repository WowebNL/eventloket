<?php

use App\Enums\Role;
use App\Filament\Resources\AdminUserResource\Pages\ListAdminUsers;
use App\Filament\Resources\AdminUserResource\Widgets\PendingAdminInvitesWidget;
use App\Livewire\AcceptInvites\AcceptAdminInvite;
use App\Mail\AdminInviteMail;
use App\Models\AdminInvite;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Mail::fake();

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);
});

test('admin can create an admin invite', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'newadmin@example.com';

    // Act
    $response = livewire(ListAdminUsers::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
        ]);

    // Assert
    $response->assertSuccessful();
    $invite = AdminInvite::where('email', $inviteeEmail)->first();

    expect($invite)->not->toBeNull()
        ->and($invite->email)->toBe($inviteeEmail);

    Mail::assertSent(AdminInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('admin can see and delete pending admin invites', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'newadmin@example.com';
    $invitee2Email = 'newadmin2@example.com';

    $invite1 = AdminInvite::factory()->create(['email' => $inviteeEmail]);
    $invite2 = AdminInvite::factory()->create(['email' => $invitee2Email]);

    // Act
    $listPage = livewire(ListAdminUsers::class)
        ->assertActionExists('pending-invites')
        ->assertActionEnabled('pending-invites')
        ->callAction('pending-invites');

    // Assert the action opens successfully
    $listPage->assertSuccessful();

    // Test the widget content directly
    $widget = livewire(PendingAdminInvitesWidget::class)
        ->assertCanSeeTableRecords([$invite1, $invite2])
        ->assertSee($inviteeEmail)
        ->assertSee($invitee2Email);

    // Test deleting a single record
    $widget->callTableAction('delete', $invite1->id)
        ->assertCanNotSeeTableRecords([$invite1])
        ->assertCanSeeTableRecords([$invite2]);

    // Verify the invite was actually deleted from the database
    expect(AdminInvite::find($invite1->id))->toBeNull()
        ->and(AdminInvite::find($invite2->id))->not->toBeNull();
});

test('existing user can accept an admin invite', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existingadmin@example.com',
    ]);

    $invite = AdminInvite::create([
        'email' => $user->email,
        'token' => Str::uuid(),
    ]);

    // Act
    $this->actingAs($user);
    $signedUrl = URL::signedRoute('admin-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdminInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    $response->assertRedirect(route('filament.admin.pages.dashboard'));

    // User should already have admin role
    $user->refresh();
    expect($user->role)->toBe(Role::Admin);

    $this->assertDatabaseMissing('admin_invites', [
        'id' => $invite->id,
    ]);
});

test('new user can register and accept an admin invite', function () {
    // Arrange
    $inviteeEmail = 'brandnewadmin@example.com';
    $invite = AdminInvite::create([
        'email' => $inviteeEmail,
        'token' => Str::uuid(),
    ]);

    $signedUrl = URL::signedRoute('admin-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdminInvite::class);

    // Test the registration and accept invite action
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->fillForm([
            'name' => 'New Global Admin',
            'phone' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create');

    // Assert
    $response->assertRedirect(route('filament.admin.pages.dashboard'));

    $user = User::where('email', $inviteeEmail)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Global Admin')
        ->and($user->phone)->toBe('1234567890')
        ->and($user->role)->toBe(Role::Admin);

    $this->assertDatabaseMissing('admin_invites', [
        'id' => $invite->id,
    ]);
});

test('invite cannot be accepted by wrong user', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = AdminInvite::create([
        'email' => 'differentuser@example.com', // Different email than logged-in user
        'token' => Str::uuid(),
    ]);

    // Act
    $this->actingAs($user);
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    // Assert
    $response->assertStatus(403);

    $this->assertDatabaseHas('admin_invites', [
        'id' => $invite->id,
    ]);
});

test('non-admin users cannot create admin invites', function () {
    // Arrange
    $nonAdmin = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::Reviewer,
    ]);

    $this->actingAs($nonAdmin);
    $inviteeEmail = 'newadmin@example.com';

    // Act & Assert - should get an authorization error
    $this->get(route('filament.admin.resources.admin-users.index'))
        ->assertForbidden();
});
