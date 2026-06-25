<?php

use App\Filament\Shared\Exports\AdvisorEventExporter;
use App\Filament\Shared\Exports\BaseEventExporter;
use App\Filament\Shared\Exports\ExtendedEventExporter;

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
