<?php

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Advisor\Widgets\AdviceThreadInboxWidget;
use App\Filament\Shared\Resources\Threads\Actions\RequestAdviceAction;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\AdviceThreadRelationManager;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages\ViewAdviceThread;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

covers(AdviceThreadInboxWidget::class);

beforeEach(function () {
    $this->advisory = Advisory::factory()->create([
        'name' => 'Test Advisory',
    ]);

    $this->advisor = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisory->users()->attach($this->advisor, ['role' => AdvisoryRole::Member]);

    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
    ]);
});

// test('advisor cannot see concept threads in AdviceThreadInboxWidget', function () {
//    Filament::setCurrentPanel(Filament::getPanel('advisor'));
//    $this->actingAs($this->advisor);
//    Filament::setTenant($this->advisory);
//
//    // Create a concept thread
//    $conceptThread = AdviceThread::forceCreate([
//        'zaak_id' => $this->zaak->id,
//        'type' => ThreadType::Advice,
//        'advisory_id' => $this->advisory->id,
//        'advice_status' => AdviceStatus::Concept,
//        'advice_due_at' => now()->addDays(7),
//        'created_by' => null,
//        'title' => 'Concept Thread',
//    ]);
//
//    // Create an asked thread
//    $askedThread = AdviceThread::forceCreate([
//        'zaak_id' => $this->zaak->id,
//        'type' => ThreadType::Advice,
//        'advisory_id' => $this->advisory->id,
//        'advice_status' => AdviceStatus::Asked,
//        'advice_due_at' => now()->addDays(7),
//        'created_by' => null,
//        'title' => 'Asked Thread',
//    ]);
//
//    // Test the widget
//    livewire(AdviceThreadInboxWidget::class)
//        ->assertSuccessful()
//        ->assertCanSeeTableRecords([$askedThread])
//        ->assertCanNotSeeTableRecords([$conceptThread]);
// });

test('advisor cannot see concept threads in AdviceThreadRelationManager', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    // Create a concept thread
    $conceptThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Concept Thread',
    ]);

    // Create an asked thread
    $askedThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Asked Thread',
    ]);

    // Test the relation manager
    livewire(AdviceThreadRelationManager::class, [
        'ownerRecord' => $this->zaak,
        'pageClass' => ViewZaak::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$askedThread])
        ->assertCanNotSeeTableRecords([$conceptThread]);
});

test('advisor cannot view concept thread page directly', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    // Create a concept thread
    $conceptThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Concept Thread',
    ]);

    // Try to view the concept thread page
    livewire(ViewAdviceThread::class, [
        'record' => $conceptThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertForbidden();
});

test('advisor can view asked thread page', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    // Create an asked thread
    $askedThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Asked Thread',
    ]);

    // Should be able to view the asked thread page
    livewire(ViewAdviceThread::class, [
        'record' => $askedThread->id,
        'parentRecord' => $this->zaak,
    ])
        ->assertSuccessful();
});

// test('advisor can see thread in widget after it transitions from concept to asked', function () {
//    Filament::setCurrentPanel(Filament::getPanel('advisor'));
//    $this->actingAs($this->advisor);
//    Filament::setTenant($this->advisory);
//
//    // Create a concept thread
//    $thread = AdviceThread::forceCreate([
//        'zaak_id' => $this->zaak->id,
//        'type' => ThreadType::Advice,
//        'advisory_id' => $this->advisory->id,
//        'advice_status' => AdviceStatus::Concept,
//        'advice_due_at' => now()->addDays(7),
//        'created_by' => null,
//        'title' => 'Test Thread',
//    ]);
//
//    // Verify thread is not visible in widget
//    livewire(AdviceThreadInboxWidget::class)
//        ->assertSuccessful()
//        ->assertCanNotSeeTableRecords([$thread]);
//
//    // Transition from Concept to Asked
//    $action = RequestAdviceAction::make();
//    $action->record($thread);
//    $action->call();
//
//    $thread->refresh();
//
//    // Verify thread is now visible in widget
//    livewire(AdviceThreadInboxWidget::class)
//        ->assertSuccessful()
//        ->assertCanSeeTableRecords([$thread]);
// });

// --- AdviceThreadInboxWidget tests ---

test('AdviceThreadInboxWidget hides concept threads', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $conceptThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Concept Thread',
    ]);

    $askedThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Asked Thread',
    ]);

    livewire(AdviceThreadInboxWidget::class)
        ->assertSuccessful()
        ->filterTable('unread', 'all')
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$askedThread])
        ->assertCanNotSeeTableRecords([$conceptThread]);
});

test('AdviceThreadInboxWidget shows threads with any non-concept status', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $inProgressThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::InProgress,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'In Progress Thread',
    ]);

    $repliedThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::AdvisoryReplied,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Replied Thread',
    ]);

    livewire(AdviceThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->assertCanSeeTableRecords([$inProgressThread, $repliedThread]);
});

test('AdviceThreadInboxWidget only shows threads for current advisory', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $otherAdvisory = Advisory::factory()->create(['name' => 'Other Advisory']);

    $ownThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Own Advisory Thread',
    ]);

    $otherThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $otherAdvisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Other Advisory Thread',
    ]);

    livewire(AdviceThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->assertCanSeeTableRecords([$ownThread])
        ->assertCanNotSeeTableRecords([$otherThread]);
});

test('assign to self action is visible for unassigned advisor', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Unassigned Thread',
    ]);

    livewire(AdviceThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->assertTableActionVisible('assign_to_self', $thread);
});

test('assign to self action is hidden when advisor is already assigned', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::InProgress,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Already Assigned Thread',
    ]);
    $thread->assignedUsers()->attach($this->advisor->id);

    livewire(AdviceThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->filterTable('assigned', 'all')
        ->assertTableActionHidden('assign_to_self', $thread);
});

test('assign to self action attaches advisor and transitions status to InProgress', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Thread to assign',
    ]);

    livewire(AdviceThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->callTableAction('assign_to_self', $thread);

    expect($thread->fresh()->advice_status)->toBe(AdviceStatus::InProgress)
        ->and($thread->assignedUsers()->where('user_id', $this->advisor->id)->exists())->toBeTrue();
});

test('assign action is visible for advisory admin', function () {
    $adminAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($adminAdvisor, ['role' => AdvisoryRole::Admin]);

    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($adminAdvisor);
    Filament::setTenant($this->advisory);

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Thread for admin',
    ]);

    livewire(AdviceThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->assertTableActionVisible('assign', $thread);
});

test('assign action is hidden for advisory member', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor); // $this->advisor is a Member in beforeEach
    Filament::setTenant($this->advisory);

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Thread for member',
    ]);

    livewire(AdviceThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->assertTableActionHidden('assign', $thread);
});
