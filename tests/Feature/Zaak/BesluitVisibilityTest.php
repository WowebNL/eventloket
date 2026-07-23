<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

function fakeBesluit(string $verzenddatum, string $documentStatus): Zaak
{
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $besluitUrl = ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1';
    $besluittypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/besluittypen/1';
    $docUrl = ZgwHttpFake::fakeSingleDocument('1', ['status' => $documentStatus]);

    Http::fake([
        ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten?*' => Http::response(ZgwHttpFake::envelope([[
            'url' => $besluitUrl,
            'identificatie' => 'BES-1',
            'besluittype' => $besluittypeUrl,
            'zaak' => $zaakUrl,
            'datum' => '2026-01-01',
            'toelichting' => 'Test besluit',
            'ingangsdatum' => '2026-01-01',
            'verzenddatum' => $verzenddatum,
            'vervaldatum' => null,
        ]]), 200),
        ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten?*' => Http::response(ZgwHttpFake::envelope([[
            'url' => ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten/1',
            'besluit' => $besluitUrl,
            'informatieobject' => $docUrl,
        ]]), 200),
        $besluittypeUrl => Http::response([
            'url' => $besluittypeUrl,
            'omschrijving' => 'Vergunning',
        ], 200),
    ]);

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();

    return Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => $zaaktype->id,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));
});

test('a definitief besluit with a reached verzenddatum is shown', function () {
    $zaak = fakeBesluit(verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(), documentStatus: 'definitief');

    expect($zaak->besluiten)->toHaveCount(1);
});

test('a besluit with a future verzenddatum is hidden', function () {
    $zaak = fakeBesluit(verzenddatum: now('Europe/Amsterdam')->addDays(3)->toDateString(), documentStatus: 'definitief');

    expect($zaak->besluiten)->toHaveCount(0);
});

test('a besluit with only a concept document is hidden', function () {
    $zaak = fakeBesluit(verzenddatum: now('Europe/Amsterdam')->subDay()->toDateString(), documentStatus: 'in_bewerking');

    expect($zaak->besluiten)->toHaveCount(0);
});
