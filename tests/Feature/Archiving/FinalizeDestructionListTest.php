<?php

use App\Enums\DestructionItemStatus;
use App\Enums\DestructionListStatus;
use App\Jobs\Archiving\FinalizeDestructionList;
use App\Jobs\Archiving\GenerateDestructionReport;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->list = DestructionList::factory()->deleting()->create();
});

test('a list where every item reached a terminal state is deleted and gets a report', function () {
    DestructionListItem::factory()->create([
        'destruction_list_id' => $this->list->id,
        'status' => DestructionItemStatus::Deleted,
    ]);

    DestructionListItem::factory()->create([
        'destruction_list_id' => $this->list->id,
        'status' => DestructionItemStatus::Skipped,
    ]);

    new FinalizeDestructionList($this->list->id)->handle();

    expect($this->list->refresh()->status)->toBe(DestructionListStatus::Deleted);

    Queue::assertPushed(GenerateDestructionReport::class);
});

test('an item that never finished counts as a failure instead of quietly passing', function (DestructionItemStatus $status) {
    DestructionListItem::factory()->create([
        'destruction_list_id' => $this->list->id,
        'status' => DestructionItemStatus::Deleted,
    ]);

    $stuck = DestructionListItem::factory()->create([
        'destruction_list_id' => $this->list->id,
        'status' => $status,
    ]);

    new FinalizeDestructionList($this->list->id)->handle();

    expect($this->list->refresh()->status)->toBe(DestructionListStatus::Failed)
        ->and($stuck->refresh()->status)->toBe(DestructionItemStatus::Failed)
        ->and($stuck->failure_reason)->not->toBeNull();

    // No proof of destruction may be issued while an item is unaccounted for.
    Queue::assertNotPushed(GenerateDestructionReport::class);
})->with([
    'still processing' => [DestructionItemStatus::Processing],
    'never started' => [DestructionItemStatus::Pending],
]);

test('an unfinished item is picked up again by a retry', function () {
    DestructionListItem::factory()->create([
        'destruction_list_id' => $this->list->id,
        'status' => DestructionItemStatus::Processing,
    ]);

    new FinalizeDestructionList($this->list->id)->handle();

    $this->list->refresh()->transitionTo(DestructionListStatus::Deleting);

    expect($this->list->items()->whereIn('status', [
        DestructionItemStatus::Pending,
        DestructionItemStatus::Processing,
        DestructionItemStatus::Failed,
    ])->count())->toBe(1);
});

test('a failed item keeps the list out of the deleted state', function () {
    DestructionListItem::factory()->create([
        'destruction_list_id' => $this->list->id,
        'status' => DestructionItemStatus::Failed,
        'failure_reason' => 'OpenZaak gaf een 500',
    ]);

    new FinalizeDestructionList($this->list->id)->handle();

    expect($this->list->refresh()->status)->toBe(DestructionListStatus::Failed);

    Queue::assertNotPushed(GenerateDestructionReport::class);
});
