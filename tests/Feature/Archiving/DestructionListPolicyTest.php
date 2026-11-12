<?php

use App\Enums\DestructionListStatus;
use App\Enums\Role;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionReport;
use App\Models\Municipality;
use App\Models\User;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();
    $this->otherMunicipality = Municipality::factory()->create();

    $this->coordinator = User::factory()->create(['role' => Role::ArchiveCoordinator]);
    $this->municipality->users()->attach($this->coordinator);

    $this->archiveReviewer = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($this->archiveReviewer);

    $this->reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($this->reviewer);
});

test('only the archive coordinator can create destruction lists', function () {
    expect($this->coordinator->can('create', DestructionList::class))->toBeTrue()
        ->and($this->archiveReviewer->can('create', DestructionList::class))->toBeFalse()
        ->and($this->reviewer->can('create', DestructionList::class))->toBeFalse();
});

test('archive roles can only view destruction lists of their own municipality', function () {
    $ownList = DestructionList::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherList = DestructionList::factory()->create(['municipality_id' => $this->otherMunicipality->id]);

    expect($this->coordinator->can('view', $ownList))->toBeTrue()
        ->and($this->archiveReviewer->can('view', $ownList))->toBeTrue()
        ->and($this->coordinator->can('view', $otherList))->toBeFalse()
        ->and($this->archiveReviewer->can('view', $otherList))->toBeFalse()
        ->and($this->reviewer->can('view', $ownList))->toBeFalse();
});

test('lists can only be updated by the coordinator while editable', function () {
    $draft = DestructionList::factory()->create(['municipality_id' => $this->municipality->id]);
    $readyToReview = DestructionList::factory()->readyToReview()->create(['municipality_id' => $this->municipality->id]);
    $changesRequested = DestructionList::factory()->create([
        'municipality_id' => $this->municipality->id,
        'status' => DestructionListStatus::ChangesRequested,
    ]);

    expect($this->coordinator->can('update', $draft))->toBeTrue()
        ->and($this->coordinator->can('update', $changesRequested))->toBeTrue()
        ->and($this->coordinator->can('update', $readyToReview))->toBeFalse()
        ->and($this->archiveReviewer->can('update', $draft))->toBeFalse();
});

test('only the archive reviewer can review a list that is ready to review', function () {
    $readyToReview = DestructionList::factory()->readyToReview()->create(['municipality_id' => $this->municipality->id]);
    $draft = DestructionList::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherList = DestructionList::factory()->readyToReview()->create(['municipality_id' => $this->otherMunicipality->id]);

    expect($this->archiveReviewer->can('review', $readyToReview))->toBeTrue()
        ->and($this->archiveReviewer->can('review', $draft))->toBeFalse()
        ->and($this->archiveReviewer->can('review', $otherList))->toBeFalse()
        ->and($this->coordinator->can('review', $readyToReview))->toBeFalse();
});

test('only the coordinator can confirm an approved list', function () {
    $approved = DestructionList::factory()->approved()->create(['municipality_id' => $this->municipality->id]);
    $readyToReview = DestructionList::factory()->readyToReview()->create(['municipality_id' => $this->municipality->id]);

    expect($this->coordinator->can('confirm', $approved))->toBeTrue()
        ->and($this->coordinator->can('confirm', $readyToReview))->toBeFalse()
        ->and($this->archiveReviewer->can('confirm', $approved))->toBeFalse();
});

test('destruction reports are read only and scoped to the municipality', function () {
    $ownReport = DestructionReport::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherReport = DestructionReport::factory()->create(['municipality_id' => $this->otherMunicipality->id]);

    expect($this->coordinator->can('view', $ownReport))->toBeTrue()
        ->and($this->archiveReviewer->can('view', $ownReport))->toBeTrue()
        ->and($this->coordinator->can('view', $otherReport))->toBeFalse()
        ->and($this->reviewer->can('view', $ownReport))->toBeFalse()
        ->and($this->coordinator->can('create', DestructionReport::class))->toBeFalse()
        ->and($this->coordinator->can('update', $ownReport))->toBeFalse()
        ->and($this->coordinator->can('delete', $ownReport))->toBeFalse();
});
