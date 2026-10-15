<?php

declare(strict_types=1);

/**
 * The calendar and its exports load zaken as Event, whose global scope selects
 * an explicit column list. The role of the zaak has to be part of it: on a
 * zaaktype shared by several roles, an event without its role falls back to
 * the role of the zaaktype's first koppeling.
 */

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function meldingOpGedeeldZaaktypeVoorEvent(): Zaak
{
    $municipality = Municipality::factory()->create();

    // Vergunning first, so a fallback to the zaaktype lands on it.
    foreach ([ZaaktypeRole::Vergunning, ZaaktypeRole::Melding] as $role) {
        MunicipalityZaaktypeMapping::create([
            'municipality_id' => $municipality->id,
            'role' => $role,
            'zaaktype_identificatie' => 'SYN-GEDEELD',
        ]);
    }

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'identificatie' => 'SYN-GEDEELD',
        'name' => 'Evenement behandelen',
        'role' => null,
        'is_active' => true,
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'role' => ZaaktypeRole::Melding,
        'organisation_id' => Organisation::factory()->create()->id,
    ]);
}

it('keeps the role of the zaak for case-handling roles', function (Role $userRole) {
    $zaak = meldingOpGedeeldZaaktypeVoorEvent();
    $this->actingAs(User::factory()->create(['role' => $userRole]));

    $event = Event::query()->whereKey($zaak->id)->firstOrFail();

    expect($event->role)->toBe(ZaaktypeRole::Melding)
        ->and($event->effectiveRole())->toBe(ZaaktypeRole::Melding)
        ->and($event->zaaktypeMapping()?->role)->toBe(ZaaktypeRole::Melding);
})->with([Role::Admin, Role::Advisor, Role::Reviewer]);

it('keeps the role of the zaak for everyone else', function () {
    $zaak = meldingOpGedeeldZaaktypeVoorEvent();
    $this->actingAs(User::factory()->create(['role' => Role::Organiser]));

    $event = Event::query()->whereKey($zaak->id)->firstOrFail();

    expect($event->role)->toBe(ZaaktypeRole::Melding)
        ->and($event->effectiveRole())->toBe(ZaaktypeRole::Melding);
});
