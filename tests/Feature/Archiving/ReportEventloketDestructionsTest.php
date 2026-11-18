<?php

use App\Enums\DestructionReportType;
use App\Enums\Role;
use App\Models\Archiving\DestructionReport;
use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Archiving\DestructionReportPdf;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->municipality = Municipality::factory()->create(['brk_identification' => 'GM0935']);
});

test('the previous day destructions become one report per municipality', function () {
    $other = Municipality::factory()->create(['brk_identification' => 'GM0888']);

    ZaakDestructionLog::factory()->count(3)->create([
        'municipality_id' => $this->municipality->id,
        'destroyed_at' => now()->subDay(),
    ]);

    ZaakDestructionLog::factory()->create([
        'municipality_id' => $other->id,
        'destroyed_at' => now()->subDay(),
    ]);

    $this->artisan('archiving:report-eventloket-destructions')->assertSuccessful();

    expect(DestructionReport::count())->toBe(2);

    $report = DestructionReport::where('municipality_id', $this->municipality->id)->sole();

    expect($report->type)->toBe(DestructionReportType::EventloketData)
        ->and($report->destruction_list_id)->toBeNull()
        ->and($report->coordinator_name)->toBeNull()
        ->and($report->total_count)->toBe(3)
        ->and($report->items)->toHaveCount(3)
        ->and($report->batch_number)->toStartWith('VL-GM0935-')
        ->and($report->hasPdf())->toBeTrue();
});

test('reported rows are marked and never reported again', function () {
    ZaakDestructionLog::factory()->count(2)->create([
        'municipality_id' => $this->municipality->id,
        'destroyed_at' => now()->subDay(),
    ]);

    $this->artisan('archiving:report-eventloket-destructions')->assertSuccessful();

    $report = DestructionReport::sole();

    expect(ZaakDestructionLog::whereNull('reported_at')->count())->toBe(0)
        ->and(ZaakDestructionLog::where('destruction_report_id', $report->id)->count())->toBe(2);

    $this->artisan('archiving:report-eventloket-destructions')->assertSuccessful();

    expect(DestructionReport::count())->toBe(1);
});

test('a row an earlier run missed is still picked up', function () {
    // Destroyed three days ago but never reported: an upper bound rather than a
    // single-day range means it is not stranded.
    ZaakDestructionLog::factory()->create([
        'municipality_id' => $this->municipality->id,
        'destroyed_at' => now()->subDays(3),
    ]);

    $this->artisan('archiving:report-eventloket-destructions')->assertSuccessful();

    expect(DestructionReport::sole()->total_count)->toBe(1);
});

test('destructions from today are left for tomorrow', function () {
    ZaakDestructionLog::factory()->create([
        'municipality_id' => $this->municipality->id,
        'destroyed_at' => now(),
    ]);

    $this->artisan('archiving:report-eventloket-destructions')
        ->expectsOutput('Nothing to report.')
        ->assertSuccessful();

    expect(DestructionReport::count())->toBe(0);
});

test('a dry run writes nothing', function () {
    ZaakDestructionLog::factory()->create([
        'municipality_id' => $this->municipality->id,
        'destroyed_at' => now()->subDay(),
    ]);

    $this->artisan('archiving:report-eventloket-destructions', ['--dry-run' => true])->assertSuccessful();

    expect(DestructionReport::count())->toBe(0)
        ->and(ZaakDestructionLog::whereNull('reported_at')->count())->toBe(1);
});

test('the eventloket report shares the municipality batch number sequence', function () {
    DestructionReport::factory()->create([
        'municipality_id' => $this->municipality->id,
        'batch_number' => 'VL-GM0935-'.now()->year.'-001',
    ]);

    ZaakDestructionLog::factory()->create([
        'municipality_id' => $this->municipality->id,
        'destroyed_at' => now()->subDay(),
    ]);

    $this->artisan('archiving:report-eventloket-destructions')->assertSuccessful();

    expect(DestructionReport::where('type', DestructionReportType::EventloketData)->sole()->batch_number)
        ->toBe('VL-GM0935-'.now()->year.'-002');
});

test('a report whose pdf is missing can be rendered again from its stored row', function () {
    // The rows are committed as reported before the PDF is written, so a job
    // dying in between leaves a report with no readable proof. The nightly
    // command will not produce it again, so this is the only way back.
    ZaakDestructionLog::factory()->create([
        'municipality_id' => $this->municipality->id,
        'destroyed_at' => now()->subDay(),
    ]);

    $this->artisan('archiving:report-eventloket-destructions')->assertSuccessful();

    $report = DestructionReport::sole();

    Storage::disk('local')->delete($report->pdf_path);

    expect($report->hasPdf())->toBeFalse();

    app(DestructionReportPdf::class)->store($report);

    expect($report->refresh()->hasPdf())->toBeTrue()
        // The record itself is untouched: only the file was rebuilt.
        ->and($report->batch_number)->toBe(DestructionReport::sole()->batch_number)
        ->and($report->total_count)->toBe(1);
});

test('the coordinator may repair a missing pdf and nobody may change the report', function () {
    $coordinator = User::factory()->create(['role' => Role::ArchiveCoordinator]);
    $this->municipality->users()->attach($coordinator);

    $reviewer = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($reviewer);

    $report = DestructionReport::factory()->create([
        'municipality_id' => $this->municipality->id,
        'type' => DestructionReportType::EventloketData,
    ]);

    expect($coordinator->can('regenerate', $report))->toBeTrue()
        ->and($reviewer->can('regenerate', $report))->toBeFalse()
        ->and($coordinator->can('update', $report))->toBeFalse()
        ->and($coordinator->can('delete', $report))->toBeFalse();
});
