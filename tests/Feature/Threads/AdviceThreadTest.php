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
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\NewAdviceThread;
use App\Notifications\NewAdviceThreadMessage;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->advisory = Advisory::factory()->create([
        'name' => 'Brandweer',
    ]);

    $this->advisor = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisor2 = User::factory()->create([
        'email' => 'advisor2@example.com',
        'role' => Role::Advisor,
    ]);

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
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    livewire(CreateAdviceThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'advisory_id' => $this->advisory->id,
            'advice_due_at' => now()->addDays(10),
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

    // Should have been sent to both advisors
    Notification::assertSentTo(
        [$this->advisor, $this->advisor2],
        NewAdviceThread::class,
    );

    // Because this is the first message, no new message email should have been sent
    Notification::assertNotSentTo(
        [$this->advisor, $this->advisor2],
        NewAdviceThreadMessage::class
    );

    // Unread message entries should have been created
    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->advisor->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->advisor2->id,
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

    // Everybody except for the advisor who sent the message received an email
    Notification::assertSentTo(
        [$this->reviewer, $this->reviewer2, $this->advisor2],
        NewAdviceThreadMessage::class
    );

    Notification::assertNotSentTo(
        [$this->advisor],
        NewAdviceThreadMessage::class
    );

    $message = $adviceThread->messages()->first();

    // Unread message entries should have been created
    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->reviewer2->id,
        'message_id' => $message->id,
    ]);

    $this->assertDatabaseHas('unread_messages', [
        'user_id' => $this->advisor2->id,
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
test('advisor can only see advice threads from their advisory', function () {
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
        ->assertCanSeeTableRecords([$myAdviceThread])
        ->assertCanNotSeeTableRecords([$otherAdviceThread]);
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
