<?php

use App\Enums\DestructionListStatus;
use App\Enums\Role;
use App\Filament\Municipality\Clusters\Archiving;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages\EditDestructionList;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages\ListDestructionLists;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages\ViewDestructionList;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionReportResource\Pages\ViewDestructionReport;
use App\Jobs\Archiving\GenerateDestructionReport;
use App\Jobs\Archiving\StartDestructionListDeletion;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use App\Models\Archiving\DestructionReport;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\DestructionListReadyForReview;
use App\Notifications\DestructionListReviewed;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create();

    $this->coordinator = User::factory()->create(['role' => Role::ArchiveCoordinator]);
    $this->municipality->users()->attach($this->coordinator);

    $this->archiveReviewer = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($this->archiveReviewer);
});

function actAsInMunicipality(User $user, Municipality $municipality): void
{
    test()->actingAs($user);

    Filament::setTenant($municipality);
    Filament::bootCurrentPanel();
}

test('archive roles can access the archiving cluster, other roles cannot', function () {
    actAsInMunicipality($this->coordinator, $this->municipality);
    expect(Archiving::canAccess())->toBeTrue();

    actAsInMunicipality($this->archiveReviewer, $this->municipality);
    expect(Archiving::canAccess())->toBeTrue();

    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);

    actAsInMunicipality($reviewer, $this->municipality);
    expect(Archiving::canAccess())->toBeFalse();
});

test('destruction lists are scoped to the tenant municipality', function () {
    $ownList = DestructionList::factory()->create(['municipality_id' => $this->municipality->id]);

    $otherMunicipality = Municipality::factory()->create();
    $otherList = DestructionList::factory()->create(['municipality_id' => $otherMunicipality->id]);

    actAsInMunicipality($this->coordinator, $this->municipality);

    livewire(ListDestructionLists::class)
        ->assertCanSeeTableRecords([$ownList])
        ->assertCanNotSeeTableRecords([$otherList]);
});

test('the full review workflow: submit, approve and confirm', function () {
    Notification::fake();
    Queue::fake();

    $list = DestructionList::factory()->create([
        'municipality_id' => $this->municipality->id,
        'created_by_user_id' => $this->coordinator->id,
    ]);

    DestructionListItem::factory()->create(['destruction_list_id' => $list->id]);

    // 1. The coordinator submits the list for review
    actAsInMunicipality($this->coordinator, $this->municipality);

    livewire(EditDestructionList::class, ['record' => $list->getRouteKey()])
        ->callAction('submit_for_review')
        ->assertHasNoActionErrors();

    expect($list->refresh()->status)->toBe(DestructionListStatus::ReadyToReview);

    Notification::assertSentTo($this->archiveReviewer, DestructionListReadyForReview::class);

    // 2. The archive reviewer approves
    actAsInMunicipality($this->archiveReviewer, $this->municipality);

    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->callAction('approve')
        ->assertHasNoActionErrors();

    $list->refresh();

    expect($list->status)->toBe(DestructionListStatus::Approved)
        ->and($list->reviewed_by_user_id)->toBe($this->archiveReviewer->id)
        ->and($list->approved_at)->not->toBeNull();

    Notification::assertSentTo($this->coordinator, DestructionListReviewed::class);

    // 3. The coordinator confirms the destruction
    actAsInMunicipality($this->coordinator, $this->municipality);

    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->callAction('confirm_destruction', data: [
            'confirmation' => $list->name,
            'coordinator_function' => 'Archiefcoördinator',
            'destruction_method' => config('archiving.destruction_method'),
        ])
        ->assertHasNoActionErrors();

    $list->refresh();

    expect($list->status)->toBe(DestructionListStatus::Deleting)
        ->and($list->coordinator_name)->toBe($this->coordinator->name)
        ->and($list->confirmed_at)->not->toBeNull();

    Queue::assertPushed(StartDestructionListDeletion::class);
});

test('the reviewer can request changes with mandatory feedback', function () {
    Notification::fake();

    $list = DestructionList::factory()->readyToReview()->create([
        'municipality_id' => $this->municipality->id,
        'created_by_user_id' => $this->coordinator->id,
    ]);

    actAsInMunicipality($this->archiveReviewer, $this->municipality);

    // Feedback is mandatory
    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->callAction('request_changes', data: ['review_feedback' => ''])
        ->assertHasActionErrors(['review_feedback' => 'required']);

    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->callAction('request_changes', data: ['review_feedback' => 'Zaak 2 hoort er niet bij'])
        ->assertHasNoActionErrors();

    $list->refresh();

    expect($list->status)->toBe(DestructionListStatus::ChangesRequested)
        ->and($list->review_feedback)->toBe('Zaak 2 hoort er niet bij');

    Notification::assertSentTo($this->coordinator, DestructionListReviewed::class);
});

test('confirming with a wrong list name fails', function () {
    Queue::fake();

    $list = DestructionList::factory()->approved()->create([
        'municipality_id' => $this->municipality->id,
        'created_by_user_id' => $this->coordinator->id,
    ]);

    actAsInMunicipality($this->coordinator, $this->municipality);

    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->callAction('confirm_destruction', data: [
            'confirmation' => 'verkeerde naam',
            'coordinator_function' => 'Archiefcoördinator',
            'destruction_method' => config('archiving.destruction_method'),
        ])
        ->assertHasActionErrors();

    expect($list->refresh()->status)->toBe(DestructionListStatus::Approved);

    Queue::assertNotPushed(StartDestructionListDeletion::class);
});

test('the coordinator cannot approve their own list', function () {
    $list = DestructionList::factory()->readyToReview()->create([
        'municipality_id' => $this->municipality->id,
        'created_by_user_id' => $this->coordinator->id,
    ]);

    actAsInMunicipality($this->coordinator, $this->municipality);

    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionHidden('request_changes');
});

test('the coordinator can regenerate a destruction report that is missing', function () {
    Queue::fake();
    Storage::fake('local');

    $list = DestructionList::factory()->deleting()->create([
        'municipality_id' => $this->municipality->id,
        'created_by_user_id' => $this->coordinator->id,
    ]);

    // Destroyed, but the report job never ran: there is no proof of destruction.
    $list->transitionTo(DestructionListStatus::Deleted);

    actAsInMunicipality($this->coordinator, $this->municipality);

    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->callAction('regenerate_report')
        ->assertHasNoActionErrors();

    Queue::assertPushed(GenerateDestructionReport::class);
});

test('regenerating is hidden while the report and its pdf are readable', function () {
    Storage::fake('local');

    $report = DestructionReport::factory()->create([
        'municipality_id' => $this->municipality->id,
        'pdf_path' => 'archief/rapporten/report.pdf',
    ]);

    Storage::disk('local')->put($report->pdf_path, 'pdf');

    $list = DestructionList::factory()->deleting()->create([
        'municipality_id' => $this->municipality->id,
        'created_by_user_id' => $this->coordinator->id,
    ]);

    $list->transitionTo(DestructionListStatus::Deleted, ['destruction_report_id' => $report->id]);

    actAsInMunicipality($this->coordinator, $this->municipality);

    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->assertActionHidden('regenerate_report');
});

test('the archive reviewer cannot regenerate a report', function () {
    Storage::fake('local');

    $list = DestructionList::factory()->deleting()->create([
        'municipality_id' => $this->municipality->id,
        'created_by_user_id' => $this->coordinator->id,
    ]);

    $list->transitionTo(DestructionListStatus::Deleted);

    actAsInMunicipality($this->archiveReviewer, $this->municipality);

    livewire(ViewDestructionList::class, ['record' => $list->getRouteKey()])
        ->assertActionHidden('regenerate_report');
});

test('a report whose pdf is gone can be regenerated from the report itself', function () {
    Queue::fake();
    Storage::fake('local');

    $list = DestructionList::factory()->deleting()->create([
        'municipality_id' => $this->municipality->id,
        'created_by_user_id' => $this->coordinator->id,
    ]);

    $list->transitionTo(DestructionListStatus::Deleted);

    // The report survives, its pdf does not: the disk was replaced.
    $report = DestructionReport::factory()->create([
        'municipality_id' => $this->municipality->id,
        'destruction_list_id' => $list->id,
        'pdf_path' => 'archief/rapporten/report.pdf',
    ]);

    $list->update(['destruction_report_id' => $report->id]);

    actAsInMunicipality($this->coordinator, $this->municipality);

    livewire(ViewDestructionReport::class, ['record' => $report->getRouteKey()])
        ->assertActionHidden('download_pdf')
        ->callAction('regenerate_report')
        ->assertHasNoActionErrors();

    Queue::assertPushed(GenerateDestructionReport::class);
});
