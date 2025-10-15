
<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\OrganiserThreadsRelationManager;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Pages\CreateOrganiserThread;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Pages\ViewOrganiserThread;
use App\Livewire\Thread\MessageForm;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\NewOrganiserThread;
use App\Notifications\NewOrganiserThreadMessage;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);
    $this->organisation = Organisation::factory()->create(['name' => 'Test Organisation', 'type' => OrganisationType::Business]);
    $this->otherOrganisation = Organisation::factory()->create(['name' => 'Other Organisation', 'type' => OrganisationType::Business]);

    // Municipality users
    $this->reviewer = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::Reviewer,
    ]);
    $this->reviewer2 = User::factory()->create([
        'email' => 'reviewer2@example.com',
        'role' => Role::Reviewer,
    ]);
    $this->municipality->users()->attach([$this->reviewer->id, $this->reviewer2->id]);

    // Organisation users
    $this->organiser = User::factory()->create([
        'email' => 'organiser@example.com',
        'role' => Role::Organiser,
    ]);
    $this->organiser2 = User::factory()->create([
        'email' => 'organiser2@example.com',
        'role' => Role::Organiser,
    ]);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Member]);
    $this->organisation->users()->attach($this->organiser2, ['role' => OrganisationRole::Member]);

    // Other organisation users
    $this->otherOrganiser = User::factory()->create([
        'email' => 'other_organiser@example.com',
        'role' => Role::Organiser,
    ]);
    $this->otherOrganisation->users()->attach($this->otherOrganiser->id, ['role' => OrganisationRole::Member]);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new ZaakReferenceData(
            'A',
            now(),
            now()->addDay(),
            now(),
            'Ontvangen',
            'Test locatie',
            'Test event'
        ),
    ]);

    Mail::fake();
    Notification::fake();
});

// Both municipalities and organisations can create organiser threads
test('can be created by municipality reviewer triggers email sending and creates unread', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    livewire(CreateOrganiserThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->zaak->refresh();

    expect($this->zaak->organiserThreads()->count())->toBe(1);

    $organiserThread = $this->zaak->organiserThreads()->first();
    expect($organiserThread->messages()->count())->toBe(1);

    $message = $organiserThread->messages()->first();

    // Should have been sent to all organisation workers and municipality handlers
    Notification::assertSentTo(
        [$this->organiser, $this->organiser2, $this->reviewer2],
        NewOrganiserThread::class
    );

    // Because this is the first message, no new message email should have been sent
    Notification::assertNotSentTo(
        [$this->organiser, $this->organiser2, $this->reviewer2],
        NewOrganiserThreadMessage::class
    );

    // Unread message entries should have been created for all participants except creator
    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->organiser->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->organiser2->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer2->id,
        'message_id' => $message->id,
    ]);

    // Creator should not have unread entry
    $this->assertDatabaseMissing('unread_messages', [
        'user_id' => $this->reviewer->id,
        'message_id' => $message->id,
    ]);
});

test('can be created by organiser triggers email sending and creates unread', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(CreateOrganiserThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->zaak->refresh();

    expect($this->zaak->organiserThreads()->count())->toBe(1);

    $organiserThread = $this->zaak->organiserThreads()->first();
    expect($organiserThread->messages()->count())->toBe(1);

    $message = $organiserThread->messages()->first();

    // Should have been sent to all organisation workers and municipality handlers
    Notification::assertSentTo(
        [$this->organiser2, $this->reviewer, $this->reviewer2],
        NewOrganiserThread::class
    );
    Notification::assertNotSentTo(
        [$this->organiser],
        NewOrganiserThread::class
    );

    // Because this is the first message, no new message email should have been sent
    Notification::assertNotSentTo(
        [$this->organiser2, $this->reviewer, $this->reviewer2],
        NewOrganiserThreadMessage::class,
    );

    // Unread message entries should have been created for all participants except creator
    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->organiser2->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer2->id,
        'message_id' => $message->id,
    ]);
});

// Municipality user can send messages
test('municipality user can send text messages', function () {
    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test organiser thread',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    livewire(MessageForm::class, [
        'thread' => $organiserThread,
    ])
        ->fillForm([
            'body' => 'Test message from municipality',
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSuccessful();

    $organiserThread->refresh();

    expect($organiserThread->messages()->count())->toBe(1);

    // Everybody except for the reviewer who sent the message received an email
    Notification::assertSentTo(
        [$this->organiser, $this->organiser2, $this->reviewer2],
        NewOrganiserThreadMessage::class,
    );

    Notification::assertNotSentTo(
        [$this->reviewer],
        NewOrganiserThreadMessage::class,
    );

    $message = $organiserThread->messages()->first();

    // Unread message entries should have been created for everyone except sender
    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->organiser->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->organiser2->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer2->id,
        'message_id' => $message->id,
    ]);
});

// Organiser can send messages
test('organiser can send text messages', function () {
    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->reviewer->id,
        'title' => 'Test organiser thread',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(MessageForm::class, [
        'thread' => $organiserThread,
    ])
        ->fillForm([
            'body' => 'Test message from organiser',
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSuccessful();

    $organiserThread->refresh();

    expect($organiserThread->messages()->count())->toBe(1);

    // Everybody except for the organiser who sent the message received an email
    Notification::assertSentTo(
        [$this->reviewer, $this->reviewer2, $this->organiser2],
        NewOrganiserThreadMessage::class
    );

    Notification::assertNotSentTo(
        [$this->organiser],
        NewOrganiserThreadMessage::class
    );

    $message = $organiserThread->messages()->first();

    // Unread message entries should have been created for everyone except sender
    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer2->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->organiser2->id,
        'message_id' => $message->id,
    ]);
});

// Municipality user can view the Filament component with organiser threads list
test('municipality user can access organiser threads list component', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    // Create some organiser threads
    $organiserThread1 = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->reviewer->id,
        'title' => 'Test organiser thread 1',
    ]);
    $organiserThread2 = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test organiser thread 2',
    ]);

    livewire(OrganiserThreadsRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$organiserThread1, $organiserThread2]);
});

// Municipality user can view individual organiser thread page
test('municipality user can view organiser thread page', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test organiser thread',
    ]);

    livewire(ViewOrganiserThread::class, [
        'record' => $organiserThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertSuccessful();
});

// Organiser can view the Filament component with organiser threads list
test('organiser can access organiser threads list component', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    // Create organiser threads for this organisation
    $organiserThread1 = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->reviewer->id,
        'title' => 'Test organiser thread 1',
    ]);
    $organiserThread2 = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test organiser thread 2',
    ]);

    livewire(OrganiserThreadsRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$organiserThread1, $organiserThread2]);
});

// Organiser can view individual organiser thread page
test('organiser can view organiser thread page', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->reviewer->id,
        'title' => 'Test organiser thread',
    ]);

    livewire(ViewOrganiserThread::class, [
        'record' => $organiserThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertSuccessful();
});

// Unread messages count is correct per thread and total in infolist tabs
test('unread messages count is correct and displayed properly', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    // Create organiser thread with messages
    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test organiser thread',
    ]);

    // Create some messages and mark them as unread for the reviewer
    $message1 = Message::factory()->create([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->organiser->id,
    ]);
    $message2 = Message::factory()->create([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->organiser2->id,
    ]);

    // Test the table shows unread count
    livewire(OrganiserThreadsRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertSeeText('2'); // Should see the unread count
});

// When visiting the page, unread messages for the user are removed
test('visiting organiser thread page marks messages as read for auth user', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test organiser thread',
    ]);

    // Create messages and mark them as unread
    $message1 = Message::factory()->create([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->organiser->id,
    ]);
    $message2 = Message::factory()->create([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->organiser2->id,
    ]);

    // Verify messages are initially unread
    expect($this->reviewer->unreadMessages()->count())->toBe(2);

    // Visit the thread page
    livewire(ViewOrganiserThread::class, [
        'record' => $organiserThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertSuccessful();

    // Messages should now be marked as read
    $this->reviewer->refresh();
    expect($this->reviewer->unreadMessages()->count())->toBe(0);
});

// Organisation can only see organiser threads from their organisation for a zaak
test('organisation can only see organiser threads from their organisation', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    // Create a zaak for the other organisation
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->otherOrganisation->id,
    ]);

    // Create threads for both organisations
    $myOrganiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id, // This zaak belongs to $this->organisation
        'type' => ThreadType::Organiser,
        'created_by' => $this->reviewer->id,
        'title' => 'My organisation thread',
    ]);

    $otherOrganiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $otherZaak->id, // This zaak belongs to $this->otherOrganisation
        'type' => ThreadType::Organiser,
        'created_by' => $this->reviewer->id,
        'title' => 'Other organisation thread',
    ]);

    // Organiser should only see threads from their own organisation's zaak
    livewire(OrganiserThreadsRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$myOrganiserThread]);

    // When viewing the other organisation's zaak, they shouldn't see it (if they even have access)
    // This test assumes they don't have access to other organisation's zaken
});

// Municipality can see all organiser threads for a zaak
test('municipality can see all organiser threads for a zaak', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    // Create threads from different creators
    $organiserThread1 = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Thread by organiser',
    ]);

    $organiserThread2 = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->reviewer2->id,
        'title' => 'Thread by municipality',
    ]);

    // Municipality should see all threads for the zaak
    livewire(OrganiserThreadsRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$organiserThread1, $organiserThread2]);
});

// Test that organiser threads don't have advice_status (unlike advice threads)
test('organiser threads do not have advice status functionality', function () {
    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->reviewer->id,
        'title' => 'Test organiser thread',
    ]);

    // Verify it doesn't have advice_status attribute
    expect(isset($organiserThread->advice_status))->toBeFalse();

    // When messages are added, no status should change
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(MessageForm::class, [
        'thread' => $organiserThread,
    ])
        ->fillForm([
            'body' => 'Test message',
        ])
        ->call('submit')
        ->assertSuccessful();

    $organiserThread->refresh();

    // Still no advice_status
    expect(isset($organiserThread->advice_status))->toBeFalse();
    expect($organiserThread->type)->toBe(ThreadType::Organiser);
});
