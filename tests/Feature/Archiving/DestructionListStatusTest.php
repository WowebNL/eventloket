<?php

use App\Enums\DestructionListStatus;
use App\Models\Archiving\DestructionList;

test('allowed status transitions succeed', function (DestructionListStatus $from, DestructionListStatus $to) {
    $list = DestructionList::factory()->create(['status' => $from]);

    $list->transitionTo($to);

    expect($list->refresh()->status)->toBe($to);
})->with([
    'draft to ready_to_review' => [DestructionListStatus::Draft, DestructionListStatus::ReadyToReview],
    'ready_to_review to changes_requested' => [DestructionListStatus::ReadyToReview, DestructionListStatus::ChangesRequested],
    'ready_to_review to approved' => [DestructionListStatus::ReadyToReview, DestructionListStatus::Approved],
    'changes_requested to ready_to_review' => [DestructionListStatus::ChangesRequested, DestructionListStatus::ReadyToReview],
    'approved to deleting' => [DestructionListStatus::Approved, DestructionListStatus::Deleting],
    'deleting to deleted' => [DestructionListStatus::Deleting, DestructionListStatus::Deleted],
    'deleting to failed' => [DestructionListStatus::Deleting, DestructionListStatus::Failed],
    'failed to deleting (retry)' => [DestructionListStatus::Failed, DestructionListStatus::Deleting],
]);

test('invalid status transitions throw', function (DestructionListStatus $from, DestructionListStatus $to) {
    $list = DestructionList::factory()->create(['status' => $from]);

    expect(fn () => $list->transitionTo($to))->toThrow(InvalidArgumentException::class);

    expect($list->refresh()->status)->toBe($from);
})->with([
    'draft to approved' => [DestructionListStatus::Draft, DestructionListStatus::Approved],
    'draft to deleting' => [DestructionListStatus::Draft, DestructionListStatus::Deleting],
    'ready_to_review to deleting' => [DestructionListStatus::ReadyToReview, DestructionListStatus::Deleting],
    'approved to deleted' => [DestructionListStatus::Approved, DestructionListStatus::Deleted],
    'deleted to deleting' => [DestructionListStatus::Deleted, DestructionListStatus::Deleting],
    'deleted to draft' => [DestructionListStatus::Deleted, DestructionListStatus::Draft],
]);
