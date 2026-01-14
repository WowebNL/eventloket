<?php

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\AdviceThreadRelationManager;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages\CreateAdviceThread;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages\ViewAdviceThread;
use App\Livewire\Thread\MessageForm;
use App\Models\Advisory;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\NotificationPreference;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\AssignedToAdviceThread;
use App\Notifications\NewAdviceThread;
use App\Notifications\NewAdviceThreadMessage;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->advisory = Advisory::factory()->create([
        'name' => 'Brandweer',
    ]);

    $this->advisoryAdmin = User::factory()->create([
        'email' => 'admin@advisory.com',
        'role' => Role::Advisor,
    ]);

    $this->advisor = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisor2 = User::factory()->create([
        'email' => 'advisor2@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisory->users()->attach($this->advisoryAdmin, ['role' => AdvisoryRole::Admin]);
    $this->advisory->users()->attach($this->advisor, ['role' => AdvisoryRole::Member]);
    $this->advisory->users()->attach($this->advisor2, ['role' => AdvisoryRole::Member]);

    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);

    $this->reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->reviewer2 = User::factory()->create(['role' => Role::Reviewer]);

    $this->municipality->users()->attach($this->reviewer);
    $this->municipality->users()->attach($this->reviewer2);

    $this->organisation = Organisation::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    $this->adviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(14),
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    Mail::fake();
    Notification::fake();
});

test('can not be created by an advisor', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    livewire(CreateAdviceThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertForbidden();
});

test('can be created by reviewer triggers email sending and creates unread', function () {
    $this->adviceThread->delete();

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    livewire(CreateAdviceThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'advisory_id' => $this->advisory->id,
            'advice_due_at' => now()->addDays(16),
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->zaak->refresh();

    expect($this->zaak->adviceThreads()->count())->toBe(1);

    $adviceThread = $this->zaak->adviceThreads()->first();
    expect($adviceThread->messages()->count())->toBe(1);

    $message = $adviceThread->messages()->first();

    // Should have been sent to advisory admin
    Notification::assertSentTo(
        [$this->advisoryAdmin],
        NewAdviceThread::class,
    );

    // Because this is the first message, no new message email should have been sent
    Notification::assertNotSentTo(
        [$this->advisoryAdmin],
        NewAdviceThreadMessage::class
    );

    // Unread message entries should have been created
    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->advisoryAdmin->id,
        'message_id' => $message->id,
    ]);
});

test('advisor can send text messages and this changes advice status to replied', function () {
    $adviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    $adviceThread->assignedUsers()->attach($this->advisor);

    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    livewire(MessageForm::class, [
        'thread' => $adviceThread,
    ])
        ->fillForm([
            'body' => 'Test message',
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSuccessful();

    $adviceThread->refresh();

    expect($adviceThread->messages()->count())->toBe(1);
    expect($adviceThread->advice_status)->toBe(AdviceStatus::AdvisoryReplied);

    // Only the municipality user who created the thread should receive a notification
    // The advisor who sent the message should not receive a notification
    // reviewer2 should not receive a notification because they did not create the thread or send any messages
    Notification::assertSentTo(
        [$this->reviewer],
        NewAdviceThreadMessage::class
    );

    Notification::assertNotSentTo(
        [$this->advisor, $this->advisor2, $this->reviewer2],
        NewAdviceThreadMessage::class
    );

    $message = $adviceThread->messages()->first();

    // Unread message entry should have been created only for the thread creator
    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseMissing('unread_messages', [
        'user_id' => $this->reviewer2->id,
        'message_id' => $message->id,
    ]);
});

// Municipality user can view the Filament component with advice threads list
test('municipality user can access advice threads list component', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    // Create some advice threads
    $adviceThread1 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);
    $adviceThread2 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    livewire(AdviceThreadRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$adviceThread1, $adviceThread2]);
});

// Municipality user can view individual advice thread page
test('municipality user can view advice thread page', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    $adviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    livewire(ViewAdviceThread::class, [
        'record' => $adviceThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertSuccessful();
});

// Advisor can view the Filament component with advice threads list
test('advisor can access advice threads list component', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    // Create advice threads for this advisory
    $adviceThread1 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);
    $adviceThread2 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    livewire(AdviceThreadRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$adviceThread1, $adviceThread2]);
});

// Advisor can view individual advice thread page
test('advisor can view advice thread page', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $adviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    livewire(ViewAdviceThread::class, [
        'record' => $adviceThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertSuccessful();
});

// Unread messages count is correct per thread and total in infolist tabs
test('unread messages count is correct and displayed properly', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    // Create advice thread with messages
    $adviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    // Create some messages and mark them as unread for the reviewer
    $message1 = Message::factory()->create([
        'thread_id' => $adviceThread->id,
        'user_id' => $this->advisor->id,
    ]);
    $message2 = Message::factory()->create([
        'thread_id' => $adviceThread->id,
        'user_id' => $this->advisor2->id,
    ]);

    // Test the table shows unread count
    livewire(AdviceThreadRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertSeeText('2'); // Should see the unread count
});

// When visiting the page, unread messages for the user are removed
test('visiting advice thread page marks messages as read for auth user', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    $adviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    // Create messages and mark them as unread
    $message1 = Message::factory()->create([
        'thread_id' => $adviceThread->id,
        'user_id' => $this->advisor->id,
    ]);
    $message2 = Message::factory()->create([
        'thread_id' => $adviceThread->id,
        'user_id' => $this->advisor2->id,
    ]);

    // Verify messages are initially unread
    expect($this->reviewer->unreadMessages()->count())->toBe(2);

    // Visit the thread page
    livewire(ViewAdviceThread::class, [
        'record' => $adviceThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertSuccessful();

    // Messages should now be marked as read
    $this->reviewer->refresh();
    expect($this->reviewer->unreadMessages()->count())->toBe(0);
});

// Advisor can only see advice threads from their advisory for a zaak
test('advisor can see advice threads from all advisories', function () {
    // Create another advisory with different advisor
    $otherAdvisory = Advisory::factory()->create(['name' => 'Politie']);
    $otherAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $otherAdvisory->users()->attach($otherAdvisor, ['role' => AdvisoryRole::Member]);

    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    // Create threads for both advisories
    $myAdviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);
    $otherAdviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $otherAdvisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    // Advisor should only see their own advisory's threads
    livewire(AdviceThreadRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$myAdviceThread, $otherAdviceThread]);
});

// Municipality can see all advice threads for a zaak
test('municipality can see all advice threads for a zaak', function () {
    // Create another advisory
    $otherAdvisory = Advisory::factory()->create(['name' => 'Politie']);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    // Create threads for both advisories
    $adviceThread1 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);
    $adviceThread2 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $otherAdvisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    // Municipality should see all threads
    livewire(AdviceThreadRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$adviceThread1, $adviceThread2]);
});

test('admin can assign multiple advisors to advice thread using AssignAction', function () {

    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisoryAdmin);
    Filament::setTenant($this->advisory);

    expect($this->adviceThread->assignedUsers)->toHaveCount(0);
    expect($this->adviceThread->advice_status)->toBe(AdviceStatus::Asked);

    // Test the action execution
    $data = ['advisors' => [$this->advisor->id, $this->advisor2->id]];

    livewire(MessageForm::class, [
        'thread' => $this->adviceThread,
    ])
        ->callAction('assign', $data);

    $this->adviceThread->refresh();

    expect($this->adviceThread->assignedUsers)->toHaveCount(2);
    expect($this->adviceThread->assignedUsers->pluck('id')->toArray())->toContain($this->advisor->id, $this->advisor2->id);
    expect($this->adviceThread->advice_status)->toBe(AdviceStatus::InProgress);

    // Assert each advisor received AssignedToAdviceThread notificaiton
    Notification::assertSentTo(
        [$this->advisor, $this->advisor2],
        AssignedToAdviceThread::class,
    );
});

test('admin can assign single advisor to advice thread using AssignAction', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisoryAdmin);
    Filament::setTenant($this->advisory);

    expect($this->adviceThread->assignedUsers)->toHaveCount(0);
    expect($this->adviceThread->advice_status)->toBe(AdviceStatus::Asked);

    // Test the action execution
    $data = ['advisors' => [$this->advisor->id]];
    livewire(MessageForm::class, [
        'thread' => $this->adviceThread,
    ])
        ->callAction('assign', $data);

    $this->adviceThread->refresh();

    expect($this->adviceThread->assignedUsers)->toHaveCount(1);
    expect($this->adviceThread->assignedUsers->first()->id)->toBe($this->advisor->id);
    expect($this->adviceThread->advice_status)->toBe(AdviceStatus::InProgress);

    // Assert advisor received AssignedToAdviceThread notificaiton
    Notification::assertSentTo(
        [$this->advisor],
        AssignedToAdviceThread::class,
    );
});

test('AssignAction only visible to advisory admins', function () {
    // Test as regular advisory member
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    livewire(MessageForm::class, [
        'thread' => $this->adviceThread,
    ])
        ->assertActionNotMounted('assign');

    // Test as advisory admin
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisoryAdmin);
    Filament::setTenant($this->advisory);

    livewire(MessageForm::class, [
        'thread' => $this->adviceThread,
    ])
        ->assertActionVisible('assign');
});

// test('advisor can assign themselves to advice thread using AssignToSelfAction', function () {
//    Filament::setCurrentPanel(Filament::getPanel('advisor'));
//    $this->actingAs($this->advisor);
//    Filament::setTenant($this->advisory);
//
//    expect($this->adviceThread->assignedUsers)->toHaveCount(0);
//    expect($this->adviceThread->advice_status)->toBe(AdviceStatus::Asked);
//
//    livewire(MessageForm::class, [
//        'thread' => $this->adviceThread,
//    ])
//        ->callAction('assign_to_self');
//
//    $this->adviceThread->refresh();
//
//    expect($this->adviceThread->assignedUsers)->toHaveCount(1);
//    expect($this->adviceThread->assignedUsers->first()->id)->toBe($this->advisor->id);
//    expect($this->adviceThread->advice_status)->toBe(AdviceStatus::InProgress);
//
//     // Assert advisor didn't received AssignedToAdviceThread notification
//     Notification::assertNotSentTo(
//         [$this->advisor],
//         AssignedToAdviceThread::class,
//     );
// });

test('AssignToSelfAction is not visible when advisor is already assigned', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    // Assign advisor to thread first
    $this->adviceThread->assignedUsers()->attach($this->advisor->id);

    livewire(MessageForm::class, [
        'thread' => $this->adviceThread,
    ])
        ->assertActionNotMounted('assign_to_self')
        ->assertActionDisabled('assign_to_self');
});

test('AssignToSelfAction is visible when advisor is not assigned', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    livewire(MessageForm::class, [
        'thread' => $this->adviceThread,
    ])
        ->assertActionVisible('assign_to_self');
});

test('Advisory cannot add message to advice thread from another advisory', function () {
    // Create another advisory and advice thread
    $otherAdvisory = Advisory::factory()->create(['name' => 'Politie']);
    $otherAdviceThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $otherAdvisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisoryAdmin);
    Filament::setTenant($this->advisory);
    Filament::bootCurrentPanel();

    livewire(ViewAdviceThread::class, [
        'record' => $otherAdviceThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertSuccessful()
        ->assertDontSeeLivewire(MessageForm::class);
});

test('unassigned advisor cannot submit message in MessageForm', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    livewire(MessageForm::class, [
        'thread' => $this->adviceThread,
    ])
        ->call('submit')
        ->assertForbidden();
});

test('assigned advisor can submit message in MessageForm', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $this->adviceThread->assignedUsers()->attach($this->advisor->id);

    livewire(MessageForm::class, [
        'thread' => $this->adviceThread,
    ])
        ->fillForm([
            'body' => 'Test message',
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertSuccessful();

    $this->adviceThread->refresh();
    expect($this->adviceThread->messages)->toHaveCount(1);
    expect($this->adviceThread->messages->first()->user_id)->toBe($this->advisor->id);
});

test('sends new advice thread notifications to all advisory admins who have it enabled', function () {
    AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(14),
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    Notification::assertSentTo(
        [$this->advisoryAdmin],
        NewAdviceThread::class,
    );

    Notification::assertNotSentTo(
        [$this->advisor, $this->advisor2],
        NewAdviceThread::class,
    );

    Notification::fake();

    // Now disable the NewAdviceThread notification for the admin
    NotificationPreference::create([
        'user_id' => $this->advisoryAdmin->id,
        'notification_class' => NewAdviceThread::class,
        'channels' => [],
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(14),
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    Notification::assertNotSentTo(
        [$this->advisoryAdmin, $this->advisor, $this->advisor2],
        NewAdviceThread::class,
    );
});

test('sends new advice thread message notifications to all advisory admins of unassigned threads who have it enabled', function () {
    // Create unassigned advice thread
    $adviceThread2 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(14),
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    $message1 = Message::factory()->create([
        'thread_id' => $adviceThread2->id,
        'user_id' => $this->reviewer->id,
    ]);

    $message2 = Message::factory()->create([
        'thread_id' => $adviceThread2->id,
        'user_id' => $this->reviewer->id,
    ]);

    Notification::assertSentTo(
        [$this->advisoryAdmin],
        NewAdviceThreadMessage::class,
    );

    Notification::assertNotSentTo(
        [$this->advisor, $this->advisor2],
        NewAdviceThreadMessage::class,
    );

    Notification::fake();

    // Now disable the NewAdviceThreadMessage notification for the admin
    NotificationPreference::create([
        'user_id' => $this->advisoryAdmin->id,
        'notification_class' => NewAdviceThreadMessage::class,
        'channels' => [],
    ]);

    $message3 = Message::factory()->create([
        'thread_id' => $adviceThread2->id,
        'user_id' => $this->reviewer->id,
    ]);

    Notification::assertNotSentTo(
        [$this->advisoryAdmin, $this->advisor, $this->advisor2],
        NewAdviceThreadMessage::class,
    );
});
