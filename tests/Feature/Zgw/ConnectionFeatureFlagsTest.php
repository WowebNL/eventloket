<?php

declare(strict_types=1);

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;

function zaakForConnection(array $connectionAttributes = [], bool $withConnection = true): Zaak
{
    $municipality = Municipality::factory()->create();

    if ($withConnection) {
        MunicipalityZgwConnection::factory()->create(array_merge([
            'municipality_id' => $municipality->id,
        ], $connectionAttributes));
    }

    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => null,
    ]);
}

test('without a connection row all behaviour defaults to the full feature set', function () {
    $zaak = zaakForConnection(withConnection: false);

    expect($zaak->behandelaarCanChangeStatus())->toBeTrue();
    expect($zaak->showsTab('besluiten'))->toBeTrue();
    expect($zaak->showsTab('bestanden'))->toBeTrue();
    expect($zaak->showsTab('adviesvragen'))->toBeTrue();
    expect($zaak->showsTab('organisatievragen'))->toBeTrue();
    expect($zaak->suppressesNotifications())->toBeFalse();
});

test('lock_status_for_behandelaar blocks status changes', function () {
    $zaak = zaakForConnection(['lock_status_for_behandelaar' => true]);

    expect($zaak->behandelaarCanChangeStatus())->toBeFalse();
});

test('tab toggles are reflected by showsTab', function () {
    $zaak = zaakForConnection([
        'show_besluiten_tab' => false,
        'show_bestanden_tab' => false,
        'show_adviesvragen_tab' => true,
        'show_organisatievragen_tab' => false,
    ]);

    expect($zaak->showsTab('besluiten'))->toBeFalse();
    expect($zaak->showsTab('bestanden'))->toBeFalse();
    expect($zaak->showsTab('adviesvragen'))->toBeTrue();
    expect($zaak->showsTab('organisatievragen'))->toBeFalse();
});

test('suppress_notifications is reflected by suppressesNotifications', function () {
    $zaak = zaakForConnection(['suppress_notifications' => true]);

    expect($zaak->suppressesNotifications())->toBeTrue();
});
