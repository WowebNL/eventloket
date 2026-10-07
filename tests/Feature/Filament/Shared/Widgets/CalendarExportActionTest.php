<?php

use App\Enums\Role;
use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

covers(MunicipalityCalendarWidget::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-07 10:00:00');

    $this->municipality = Municipality::factory()->create();

    $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($user);
    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);
});

/**
 * The payload the calendar sends when it shows the month of October 2026,
 * with the browser two hours ahead of UTC.
 */
function octoberMonthDatesSet(): array
{
    return [
        'start' => '2026-09-27T22:00:00.000Z',
        'end' => '2026-11-07T23:00:00.000Z',
        'tzOffset' => 120,
        'view' => [
            'type' => 'dayGridMonth',
            'title' => 'October 2026',
            'currentStart' => '2026-09-30T22:00:00.000Z',
            'currentEnd' => '2026-10-31T23:00:00.000Z',
            'activeStart' => '2026-09-27T22:00:00.000Z',
            'activeEnd' => '2026-11-07T23:00:00.000Z',
        ],
    ];
}

test('geojson export with an empty end date reports a validation error instead of failing', function () {
    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->mountAction('export')
        ->fillForm([
            'start_date' => '2026-10-07',
            'end_date' => null,
        ])
        ->callAction('exportToGeojson')
        ->assertHasFormErrors(['end_date' => 'required'])
        ->assertNoFileDownloaded();
});

test('geojson export with an empty start date reports a validation error instead of failing', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->mountAction('export')
        ->fillForm([
            'start_date' => null,
            'end_date' => '2026-11-01',
        ])
        ->callAction('exportToGeojson')
        ->assertHasFormErrors(['start_date' => 'required'])
        ->assertNoFileDownloaded();
});

test('geojson export with an end date before the start date reports a validation error', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->mountAction('export')
        ->fillForm([
            'start_date' => '2026-11-01',
            'end_date' => '2026-10-01',
        ])
        ->callAction('exportToGeojson')
        ->assertHasFormErrors(['end_date' => 'after'])
        ->assertNoFileDownloaded();
});

test('geojson export with a valid period downloads a feature collection', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->mountAction('export')
        ->fillForm([
            'start_date' => '2026-10-01',
            'end_date' => '2026-11-01',
        ])
        ->callAction('exportToGeojson')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded('export_evenementen_2026-10-07_10-00-00.geojson');
});

test('export modal in the month view uses the period shown by the calendar', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->call('onDatesSetJs', octoberMonthDatesSet())
        ->mountAction('export')
        ->assertActionDataSet([
            'start_date' => '2026-10-01',
            'end_date' => '2026-11-01',
        ]);
});

test('export modal in the list view defaults the end date to one month after the start', function () {
    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->assertSet('end', null)
        ->mountAction('export')
        ->assertActionDataSet([
            'start_date' => '2026-10-07',
            'end_date' => '2026-11-07',
        ]);
});

test('export modal defaults the end date after switching to the list view', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->call('onDatesSetJs', octoberMonthDatesSet())
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertSet('end', null)
        ->mountAction('export')
        ->assertActionDataSet([
            'start_date' => '2026-10-07',
            'end_date' => '2026-11-07',
        ]);
});

test('export modal in the list view keeps the end date of the range filter', function () {
    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->set('tableFilters.range.to', '20-10-2026')
        ->assertSet('end', fn ($end) => $end?->toDateString() === '2026-10-20')
        ->mountAction('export')
        ->assertActionDataSet([
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-20',
        ]);
});
