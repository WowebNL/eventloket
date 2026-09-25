<?php

use App\Enums\DestructionReportType;
use App\Models\Archiving\DestructionReport;
use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Municipality;
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
