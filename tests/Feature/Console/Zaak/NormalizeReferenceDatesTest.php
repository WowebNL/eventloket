<?php

declare(strict_types=1);

use App\Console\Commands\Zaak\NormalizeReferenceDates;
use App\Enums\Role;
use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

covers(NormalizeReferenceDates::class);

beforeEach(function (): void {
    $this->municipality = Municipality::factory()->create();
    $this->organisation = Organisation::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->zaakWith = fn (array $dates): Zaak => Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new ZaakReferenceData(...array_merge([
            'registratiedatum' => '2026-09-01T09:00:00+02:00',
            'status_name' => 'Ontvangen',
            'statustype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            'naam_evenement' => 'Synthetic event',
        ], $dates)),
    ]);
});

/**
 * @return array<string, mixed>
 */
function storedReferenceData(Zaak $zaak): array
{
    return json_decode((string) DB::table('zaken')->where('id', $zaak->id)->value('reference_data'), true);
}

test('only counts by default and writes nothing', function () {
    $zaak = ($this->zaakWith)([
        'start_evenement' => '20261003140000',
        'eind_evenement' => '20261004230000',
    ]);
    $before = storedReferenceData($zaak);

    $this->artisan('zaak:normalize-reference-dates')
        ->expectsOutputToContain('1 with a date in the ZGW wire format')
        ->expectsOutputToContain('Dry run: nothing was written')
        ->assertSuccessful();

    expect(storedReferenceData($zaak))->toBe($before);
});

test('an explicit --dry-run writes nothing either', function () {
    $zaak = ($this->zaakWith)(['start_evenement' => '20261003140000']);
    $before = storedReferenceData($zaak);

    $this->artisan('zaak:normalize-reference-dates --dry-run')->assertSuccessful();

    expect(storedReferenceData($zaak))->toBe($before);
});

test('refuses --dry-run together with --force', function () {
    $zaak = ($this->zaakWith)(['start_evenement' => '20261003140000']);
    $before = storedReferenceData($zaak);

    $this->artisan('zaak:normalize-reference-dates --dry-run --force')->assertFailed();

    expect(storedReferenceData($zaak))->toBe($before);
});

test('converts wire-format dates to ISO 8601 with --force and leaves everything else alone', function () {
    $affected = ($this->zaakWith)([
        'start_evenement' => '20261003140000',
        'eind_evenement' => '20261204230000',
        'start_opbouw' => '20261002',
        'eind_afbouw' => '2026-10-05T12:00:00+02:00',
    ]);
    $untouched = ($this->zaakWith)([
        'start_evenement' => '2026-10-03T14:00:00+02:00',
        'eind_evenement' => '2026-10-04T23:00:00+02:00',
    ]);
    $trashed = ($this->zaakWith)(['start_evenement' => '20261003140000']);
    $trashed->delete();

    $untouchedBefore = storedReferenceData($untouched);
    $affectedBefore = storedReferenceData($affected);
    $updatedAt = DB::table('zaken')->where('id', $affected->id)->value('updated_at');

    $this->artisan('zaak:normalize-reference-dates --force')
        ->expectsOutputToContain('Scanned 3 zaak/zaken; 2 with a date in the ZGW wire format.')
        ->expectsOutputToContain('Converted the dates of 2 zaak/zaken to ISO 8601.')
        ->assertSuccessful();

    $after = storedReferenceData($affected);

    expect($after['start_evenement'])->toBe('2026-10-03T14:00:00+02:00')
        ->and($after['eind_evenement'])->toBe('2026-12-04T23:00:00+01:00')
        ->and($after['start_opbouw'])->toBe('2026-10-02T00:00:00+02:00')
        ->and($after['eind_afbouw'])->toBe('2026-10-05T12:00:00+02:00')
        // Every other field keeps its stored value.
        ->and(array_diff_key($after, array_flip(ZaakReferenceData::DATE_TIME_FIELDS)))
        ->toBe(array_diff_key($affectedBefore, array_flip(ZaakReferenceData::DATE_TIME_FIELDS)))
        ->and(storedReferenceData($untouched))->toBe($untouchedBefore)
        ->and(storedReferenceData($trashed)['start_evenement'])->toBe('2026-10-03T14:00:00+02:00')
        ->and(DB::table('zaken')->where('id', $affected->id)->value('updated_at'))->toBe($updatedAt);
});

test('is idempotent: a second run finds nothing and changes nothing', function () {
    $zaak = ($this->zaakWith)(['start_evenement' => '20261003140000', 'eind_evenement' => '20261004230000']);

    $this->artisan('zaak:normalize-reference-dates --force')->assertSuccessful();
    $afterFirst = storedReferenceData($zaak);

    $this->artisan('zaak:normalize-reference-dates --force')
        ->expectsOutputToContain('0 with a date in the ZGW wire format')
        ->expectsOutputToContain('Nothing to convert.')
        ->assertSuccessful();

    expect(storedReferenceData($zaak))->toBe($afterFirst);
});

test('reports counts only, never a zaak or its data', function () {
    $zaak = ($this->zaakWith)(['start_evenement' => '20261003140000']);

    $this->artisan('zaak:normalize-reference-dates --force')
        ->doesntExpectOutputToContain($zaak->id)
        ->doesntExpectOutputToContain($zaak->public_id)
        ->doesntExpectOutputToContain('Synthetic event')
        ->doesntExpectOutputToContain('20261003140000')
        ->assertSuccessful();
});

test('a repaired zaak is found again by the calendar month view and the list view range filter', function () {
    $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($user);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    $zaak = ($this->zaakWith)([
        'start_evenement' => '20261003140000',
        'eind_evenement' => '20261004230000',
    ]);

    $month = ['startStr' => '2026-09-28T00:00:00+02:00', 'endStr' => '2026-11-09T00:00:00+01:00', 'tzOffset' => -120];
    $range = ['from' => '2026-10-01 00:00:00', 'to' => '2026-10-31 00:00:00'];

    // Whether the wire-format value is missed before the repair depends on the
    // database: a binary text comparison puts it outside every ISO 8601 range,
    // a collation that skips punctuation does not. Only the repaired state is
    // the same on every driver, so that is what is asserted.
    $this->artisan('zaak:normalize-reference-dates --force')->assertSuccessful();

    expect(livewire(MunicipalityCalendarWidget::class)->instance()->getEventsJs($month))->toHaveCount(1);
    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->set('tableFilters.range', $range)
        ->assertCanSeeTableRecords([$zaak]);
});
