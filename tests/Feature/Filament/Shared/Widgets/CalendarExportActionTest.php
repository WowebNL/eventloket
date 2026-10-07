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
