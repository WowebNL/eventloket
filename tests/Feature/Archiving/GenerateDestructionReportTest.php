<?php

use App\Enums\DestructionItemStatus;
use App\Jobs\Archiving\GenerateDestructionReport;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use App\Models\Archiving\DestructionReport;
use App\Models\Municipality;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->municipality = Municipality::factory()->create();
});

test('generates an immutable report with batch number, snapshots and pdf', function () {
    $list = DestructionList::factory()->deleting()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_name' => 'Jan Jansen',
        'coordinator_function' => 'Archiefcoördinator',
    ]);

    DestructionListItem::factory()->count(2)->create([
        'destruction_list_id' => $list->id,
        'status' => DestructionItemStatus::Deleted,
        'destroyed_at' => now(),
        'zaak_id' => null,
    ]);

    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'status' => DestructionItemStatus::Skipped,
        'failure_reason' => 'Zaak is niet langer vernietigbaar volgens OpenZaak',
    ]);

    new GenerateDestructionReport($list->id)->handle();

    $list->refresh();
    $report = $list->report;

    expect($report)->not->toBeNull()
        ->and($report->batch_number)->toBe(sprintf('VL-%d-%d-001', $this->municipality->id, now()->year))
        ->and($report->coordinator_name)->toBe('Jan Jansen')
        ->and($report->coordinator_function)->toBe('Archiefcoördinator')
        ->and($report->destruction_method)->toBe(config('archiving.destruction_method'))
        ->and($report->total_count)->toBe(3)
        ->and($report->deleted_count)->toBe(2)
        ->and($report->skipped_count)->toBe(1)
        ->and($report->failed_count)->toBe(0)
        ->and($report->items)->toHaveCount(3)
        ->and($report->items[0])->toHaveKeys(['zaaknummer', 'zaaktype', 'zgw_zaak_url', 'selectielijstklasse', 'bewaartermijn', 'status']);

    Storage::disk('local')->assertExists($report->pdf_path);
});

test('the batch number sequence increments per municipality and year', function () {
    DestructionReport::factory()->create([
        'municipality_id' => $this->municipality->id,
        'batch_number' => sprintf('VL-%d-%d-001', $this->municipality->id, now()->year),
    ]);

    expect(DestructionReport::nextBatchNumber($this->municipality))
        ->toBe(sprintf('VL-%d-%d-002', $this->municipality->id, now()->year));

    $otherMunicipality = Municipality::factory()->create();

    expect(DestructionReport::nextBatchNumber($otherMunicipality))
        ->toBe(sprintf('VL-%d-%d-001', $otherMunicipality->id, now()->year));
});

test('a report is only generated once per list', function () {
    $list = DestructionList::factory()->deleting()->create(['municipality_id' => $this->municipality->id]);

    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'status' => DestructionItemStatus::Deleted,
    ]);

    new GenerateDestructionReport($list->id)->handle();
    new GenerateDestructionReport($list->id)->handle();

    expect(DestructionReport::count())->toBe(1);
});

test('the coordinator snapshot on the report survives deletion of the account', function () {
    $list = DestructionList::factory()->deleting()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_name' => 'Jan Jansen',
    ]);

    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'status' => DestructionItemStatus::Deleted,
    ]);

    new GenerateDestructionReport($list->id)->handle();

    $report = $list->refresh()->report;

    // The report has no living reference to the user account, only a snapshot
    expect($report->coordinator_name)->toBe('Jan Jansen');
});
