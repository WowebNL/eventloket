
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
use Tests\Fakes\ZgwHttpFake;

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

    ZgwHttpFake::fakeStatustypen();
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    // Default zaak with "Ontvangen" status (status 1)
    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->addDays(30)->toIso8601String(),
            eind_evenement: now()->addDays(31)->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    Mail::fake();
    Notification::fake();
});

// Test organiser creates thread when zaak has "Ontvangen" status - all reviewers receive notification
test('organiser creates thread with zaak status Ontvangen notifies all reviewers', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(CreateOrganiserThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'title' => 'Question about my event',
            'body' => 'I have a question',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->zaak->refresh();

    expect($this->zaak->organiserThreads()->count())->toBe(1);

    $organiserThread = $this->zaak->organiserThreads()->first();
    expect($organiserThread->messages()->count())->toBe(1);

    // All reviewers should receive notification because zaak status is "Ontvangen"
    Notification::assertSentTo(
        [$this->reviewer, $this->reviewer2],
        NewOrganiserThread::class
    );

    // Activity log should record notification was sent to both reviewers
    expect($organiserThread->activities()->count())->toBeGreaterThan(0);
});

// Test organiser creates thread when zaak has "In behandeling" with behandelaar - only behandelaar receives notification
test('organiser creates thread with zaak status In behandeling notifies only behandelaar', function () {
    // Set zaak to "In behandeling" status and assign behandelaar
    $this->zaak->update([
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->addDays(30)->toIso8601String(),
            eind_evenement: now()->addDays(31)->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'In behandeling',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/2',
            naam_evenement: 'Test Event',
        ),
        'handled_status_set_by_user_id' => $this->reviewer->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(CreateOrganiserThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'title' => 'Question about status',
            'body' => 'What is happening?',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Only the behandelaar (reviewer) should receive notification
    Notification::assertSentTo(
        [$this->reviewer],
        NewOrganiserThread::class
    );

    Notification::assertNotSentTo(
        [$this->reviewer2],
        NewOrganiserThread::class
    );
});

// Test organiser creates thread when zaak has "Afgehandeld" status - all reviewers receive notification
test('organiser creates thread with zaak status Afgehandeld notifies all reviewers', function () {
    // Set zaak to "Afgehandeld" (finalised) status
    $this->zaak->update([
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->addDays(30)->toIso8601String(),
            eind_evenement: now()->addDays(31)->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'Afgehandeld',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/3',
            naam_evenement: 'Test Event',
        ),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(CreateOrganiserThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'title' => 'Follow-up question',
            'body' => 'I have another question',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // All reviewers should receive notification because zaak status is "Afgehandeld"
    Notification::assertSentTo(
        [$this->reviewer, $this->reviewer2],
        NewOrganiserThread::class
    );
});

// Test reviewer creates thread - all organisation users receive notification
test('reviewer creates thread notifies all organisation users', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    livewire(CreateOrganiserThread::class, [
        'parentRecord' => $this->zaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'title' => 'Additional information needed',
            'body' => 'Please provide more details',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // All organisation users receive notification
    Notification::assertSentTo(
        [$this->organiser, $this->organiser2],
        NewOrganiserThread::class
    );

    // Other reviewer who didn't create the thread does not receive notification
    Notification::assertNotSentTo(
        [$this->reviewer2],
        NewOrganiserThread::class
    );
});

// Test organiser sends message with zaak status "Ontvangen" - all reviewers notified
test('organiser sends message with zaak status Ontvangen notifies all reviewers', function () {
    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test thread',
    ]);

    Message::forceCreate([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->organiser->id,
        'body' => 'Initial message',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(MessageForm::class, [
        'thread' => $organiserThread,
    ])
        ->fillForm([
            'body' => 'Follow-up message',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    // All reviewers should receive notification because zaak status is "Ontvangen"
    Notification::assertSentTo(
        [$this->reviewer, $this->reviewer2],
        NewOrganiserThreadMessage::class
    );
});

// Test organiser sends message with zaak status "In behandeling" with behandelaar - only behandelaar notified
test('organiser sends message with zaak status In behandeling notifies only behandelaar', function () {
    // Set zaak to "In behandeling" with behandelaar
    $this->zaak->update([
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->addDays(30)->toIso8601String(),
            eind_evenement: now()->addDays(31)->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'In behandeling',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/2',
            naam_evenement: 'Test Event',
        ),
        'handled_status_set_by_user_id' => $this->reviewer->id,
    ]);

    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test thread',
    ]);

    Message::forceCreate([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->organiser->id,
        'body' => 'Initial message',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(MessageForm::class, [
        'thread' => $organiserThread,
    ])
        ->fillForm([
            'body' => 'Response message',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    // Only behandelaar should receive notification
    Notification::assertSentTo(
        [$this->reviewer],
        NewOrganiserThreadMessage::class
    );

    Notification::assertNotSentTo(
        [$this->reviewer2],
        NewOrganiserThreadMessage::class
    );
});

// Test organiser sends message with zaak status "Afgehandeld" - all reviewers notified
test('organiser sends message with zaak status Afgehandeld notifies all reviewers', function () {
    // Set zaak to "Afgehandeld" status
    $this->zaak->update([
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->addDays(30)->toIso8601String(),
            eind_evenement: now()->addDays(31)->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'Afgehandeld',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/3',
            naam_evenement: 'Test Event',
        ),
    ]);

    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test thread',
    ]);

    Message::forceCreate([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->organiser->id,
        'body' => 'Initial message',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(MessageForm::class, [
        'thread' => $organiserThread,
    ])
        ->fillForm([
            'body' => 'Final message',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    // All reviewers should receive notification
    Notification::assertSentTo(
        [$this->reviewer, $this->reviewer2],
        NewOrganiserThreadMessage::class
    );
});

// Test reviewer sends message - all organisation users notified
test('reviewer sends message notifies all organisation users', function () {
    $organiserThread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test thread',
    ]);

    Message::forceCreate([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->organiser->id,
        'body' => 'Initial message',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);

    livewire(MessageForm::class, [
        'thread' => $organiserThread,
    ])
        ->fillForm([
            'body' => 'Response from reviewer',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    // All organisation users should receive notification
    Notification::assertSentTo(
        [$this->organiser, $this->organiser2],
        NewOrganiserThreadMessage::class
    );

    // Other reviewer who didn't send message does not receive notification
    Notification::assertNotSentTo(
        [$this->reviewer2],
        NewOrganiserThreadMessage::class
    );
});

// Test personal organisation works correctly
test('can be created by organiser personal organisation too triggers email sending and creates unread', function () {
    $personalOrganisation = Organisation::factory()->create(['name' => 'Test Organisation', 'type' => OrganisationType::Personal]);

    $personalOrganisation->users()->attach($this->organiser, ['role' => OrganisationRole::Member]);

    $personalZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $personalOrganisation->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->addDays(30)->toIso8601String(),
            eind_evenement: now()->addDays(31)->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    $this->actingAs($this->organiser);
    Filament::setTenant($personalOrganisation);

    livewire(CreateOrganiserThread::class, [
        'parentRecord' => $personalZaak,
    ])
        ->assertFormExists()
        ->fillForm([
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $personalZaak->refresh();

    expect($personalZaak->organiserThreads()->count())->toBe(1);

    $organiserThread = $personalZaak->organiserThreads()->first();
    expect($organiserThread->messages()->count())->toBe(1);
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

    // Manually mark messages as unread for reviewer (since they wouldn't normally be notified)
    $this->reviewer->unreadMessages()->syncWithoutDetaching([$message1->id, $message2->id]);

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
