<?php

use App\Enums\Role;
use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

covers(MunicipalityCalendarWidget::class);

beforeEach(function (): void {
    $this->municipality = Municipality::factory()->create();
    $this->organisation = Organisation::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($user);
    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    $this->zaakStartingAt = fn (string $startEvenement): Zaak => Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new ZaakReferenceData(
            registratiedatum: '2026-09-01T09:00:00+02:00',
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            start_evenement: $startEvenement,
            eind_evenement: $startEvenement,
            naam_evenement: 'Test event',
        ),
    ]);
});

test('list view range filter keeps the zaken that start within the range', function () {
    $before = ($this->zaakStartingAt)('2026-09-03T10:00:00+02:00');
    $onFrom = ($this->zaakStartingAt)('2026-09-05T10:00:00+02:00');
    $onTo = ($this->zaakStartingAt)('2026-09-25T23:30:00+02:00');
    $after = ($this->zaakStartingAt)('2026-09-26T00:30:00+02:00');

    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->set('tableFilters.range', ['from' => '2026-09-05 00:00:00', 'to' => '2026-09-25 00:00:00'])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$onFrom, $onTo])
        ->assertCanNotSeeTableRecords([$before, $after]);
});

test('list view range filter accepts a date in the configured display format', function () {
    // The pickers store their state in config('app.date_format'), so the
    // filter has to read a day-first value without handing it to the database.
    $before = ($this->zaakStartingAt)('2026-09-03T10:00:00+02:00');
    $inRange = ($this->zaakStartingAt)('2026-09-25T10:00:00+02:00');

    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->set('tableFilters.range', ['from' => '20-09-2026', 'to' => null])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$before]);
});

test('list view still renders when a zaak stores its start in a non machine readable form', function () {
    Carbon::setTestNow('2026-09-20 08:00:00');

    // A start_evenement value that Carbon reads through its Dutch locale
    // fallback, but that no database can cast to a date.
    ($this->zaakStartingAt)('3 oktober 2026 10:00');
    $readable = ($this->zaakStartingAt)('2026-09-25T10:00:00+02:00');

    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$readable]);
});
