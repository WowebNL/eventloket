<?php

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Shared\Exports\AdvisorEventExporter;
use App\Filament\Shared\Exports\BaseEventExporter;
use App\Filament\Shared\Exports\ExtendedEventExporter;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Actions\Exports\Models\Export;

covers(BaseEventExporter::class, ExtendedEventExporter::class, AdvisorEventExporter::class);

test('base event exporter exports the status color', function () {
    $names = collect(BaseEventExporter::getColumns())->map->getName();

    expect($names)->toContain('status_color');
});

test('extended event exporter inherits the status color column', function () {
    $names = collect(ExtendedEventExporter::getColumns())->map->getName();

    expect($names)->toContain('status_color');
});

test('advisor event exporter inherits the status color column', function () {
    $names = collect(AdvisorEventExporter::getColumns())->map->getName();

    expect($names)->toContain('status_color');
});

test('event exporters export the aanvraagtype of the zaak, also on a shared zaaktype', function () {
    $municipality = Municipality::factory()->create();

    // Vergunning first, so a fallback to the zaaktype would say vergunning.
    foreach ([ZaaktypeRole::Vergunning, ZaaktypeRole::Melding] as $role) {
        MunicipalityZaaktypeMapping::create([
            'municipality_id' => $municipality->id,
            'role' => $role,
            'zaaktype_identificatie' => 'SYN-GEDEELD',
        ]);
    }

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => $municipality->id,
            'identificatie' => 'SYN-GEDEELD',
            'name' => 'Evenement behandelen',
        ])->id,
        'role' => ZaaktypeRole::Melding,
    ]);

    // The exports run as a case-handling user and load the zaak as Event.
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));
    $event = Event::query()->whereKey($zaak->id)->firstOrFail();

    foreach ([BaseEventExporter::class, ExtendedEventExporter::class, AdvisorEventExporter::class] as $exporterClass) {
        $exporter = new $exporterClass(new Export, ['role' => 'Aanvraagtype'], []);

        expect($exporter($event))->toBe(['Melding']);
    }
});
