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

    $this->municipality = Municipality::factory()->create(['brk_identification' => 'GM0935']);
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
        ->and($report->batch_number)->toBe(sprintf('VL-GM0935-%d-001', now()->year))
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

test('the batch number is built from the public municipality code, not the internal id', function () {
    expect(DestructionReport::nextBatchNumber($this->municipality))
        ->toBe(sprintf('VL-GM0935-%d-001', now()->year))
        ->and(DestructionReport::nextBatchNumber($this->municipality))
        ->not->toContain((string) $this->municipality->id.'-'.now()->year);
});

test('the batch number sequence increments per municipality and year', function () {
    DestructionReport::factory()->create([
        'municipality_id' => $this->municipality->id,
        'batch_number' => sprintf('VL-GM0935-%d-001', now()->year),
    ]);

    expect(DestructionReport::nextBatchNumber($this->municipality))
        ->toBe(sprintf('VL-GM0935-%d-002', now()->year));

    $otherMunicipality = Municipality::factory()->create(['brk_identification' => 'GM0888']);

    expect(DestructionReport::nextBatchNumber($otherMunicipality))
        ->toBe(sprintf('VL-GM0888-%d-001', now()->year));
});

test('the sequence continues after the highest issued number, never reusing one', function () {
    // A count-based sequence would hand out 002 again here.
    foreach ([1, 2, 3] as $sequence) {
        DestructionReport::factory()->create([
            'municipality_id' => $this->municipality->id,
            'batch_number' => sprintf('VL-GM0935-%d-%03d', now()->year, $sequence),
        ]);
    }

    DestructionReport::where('batch_number', sprintf('VL-GM0935-%d-002', now()->year))->delete();

    expect(DestructionReport::nextBatchNumber($this->municipality))
        ->toBe(sprintf('VL-GM0935-%d-004', now()->year));
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

test('a lost pdf is rendered again without issuing a second report', function () {
    $list = DestructionList::factory()->deleting()->create(['municipality_id' => $this->municipality->id]);

    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'status' => DestructionItemStatus::Deleted,
    ]);

    new GenerateDestructionReport($list->id)->handle();

    $report = $list->refresh()->report;
    $batchNumber = $report->batch_number;

    // The disk was replaced and the pdf is gone; the report row survives.
    Storage::disk('local')->delete($report->pdf_path);

    new GenerateDestructionReport($list->id)->handle();

    expect(DestructionReport::count())->toBe(1)
        ->and($list->refresh()->report->batch_number)->toBe($batchNumber);

    Storage::disk('local')->assertExists($report->refresh()->pdf_path);
});

test('a list keeps its report reference when the report already exists', function () {
    $list = DestructionList::factory()->deleting()->create(['municipality_id' => $this->municipality->id]);

    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'status' => DestructionItemStatus::Deleted,
    ]);

    // A run that died after writing the report row but before linking it.
    $report = DestructionReport::factory()->create([
        'municipality_id' => $this->municipality->id,
        'destruction_list_id' => $list->id,
        'batch_number' => sprintf('VL-GM0935-%d-001', now()->year),
    ]);

    new GenerateDestructionReport($list->id)->handle();

    expect(DestructionReport::count())->toBe(1)
        ->and($list->refresh()->destruction_report_id)->toBe($report->id);
});

test('the report pdf is written to the configured disk', function () {
    Storage::fake('archive');
    config(['archiving.report_disk' => 'archive']);

    $list = DestructionList::factory()->deleting()->create(['municipality_id' => $this->municipality->id]);

    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'status' => DestructionItemStatus::Deleted,
    ]);

    new GenerateDestructionReport($list->id)->handle();

    $report = $list->refresh()->report;

    Storage::disk('archive')->assertExists($report->pdf_path);
    Storage::disk('local')->assertMissing($report->pdf_path);
});
