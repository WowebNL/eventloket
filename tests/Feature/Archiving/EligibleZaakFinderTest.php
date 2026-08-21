<?php

use App\Enums\DestructionListStatus;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Archiving\EligibleZaakFinder;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $this->otherMunicipality = Municipality::factory()->create();
    $this->otherZaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->otherMunicipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/2',
    ]);
});

function fakeEligibleZaakResults(array $resultsByZaaktypeUrl): void
{
    Http::fake(function ($request) use ($resultsByZaaktypeUrl) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return Http::response([
            'count' => count($resultsByZaaktypeUrl[$query['zaaktype'] ?? ''] ?? []),
            'next' => null,
            'results' => $resultsByZaaktypeUrl[$query['zaaktype'] ?? ''] ?? [],
        ], 200);
    });
}

function zgwZaakResult(string $url, array $overrides = []): array
{
    return array_merge([
        'url' => $url,
        'archiefnominatie' => 'vernietigen',
        'archiefactiedatum' => now()->subDay()->format('Y-m-d'),
        'archiefstatus' => 'nog_te_archiveren',
        '_expand' => [
            'resultaat' => [
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/resultaten/1',
                '_expand' => [
                    'resultaattype' => [
                        'omschrijving' => 'Verleend',
                        'selectielijstklasse' => 'https://selectielijst.openzaak.nl/api/v1/resultaten/abc',
                        'archiefactietermijn' => 'P1Y',
                        'brondatumArchiefprocedure' => [
                            'afleidingswijze' => 'afgehandeld',
                        ],
                    ],
                ],
            ],
        ],
    ], $overrides);
}

test('finds local zaken that are eligible according to openzaak with their grounds', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    fakeEligibleZaakResults([
        $this->zaaktype->zgw_zaaktype_url => [
            zgwZaakResult($zaak->zgw_zaak_url),
        ],
    ]);

    $eligible = app(EligibleZaakFinder::class)->find($this->municipality);

    expect($eligible)->toHaveCount(1);

    $eligibleZaak = $eligible->first();

    expect($eligibleZaak->zaak->id)->toBe($zaak->id)
        ->and($eligibleZaak->archiefnominatie)->toBe('vernietigen')
        ->and($eligibleZaak->selectielijstklasse)->toBe('https://selectielijst.openzaak.nl/api/v1/resultaten/abc')
        ->and($eligibleZaak->selectielijstCategorie)->toBe('Verleend')
        ->and($eligibleZaak->bewaartermijn)->toBe('P1Y')
        ->and($eligibleZaak->brondatumArchiefprocedure)->toBe('afgehandeld');
});

test('zaken from another municipality are not returned', function () {
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->otherZaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/2',
    ]);

    fakeEligibleZaakResults([
        $this->otherZaaktype->zgw_zaaktype_url => [
            zgwZaakResult($otherZaak->zgw_zaak_url),
        ],
    ]);

    expect(app(EligibleZaakFinder::class)->find($this->municipality))->toBeEmpty();
});

test('zgw zaken without a local match are not returned', function () {
    fakeEligibleZaakResults([
        $this->zaaktype->zgw_zaaktype_url => [
            zgwZaakResult(ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/unknown'),
        ],
    ]);

    expect(app(EligibleZaakFinder::class)->find($this->municipality))->toBeEmpty();
});

test('zaken that are already on an open destruction list are not returned', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $list = DestructionList::factory()->create(['municipality_id' => $this->municipality->id]);
    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'zaak_id' => $zaak->id,
        'zgw_zaak_url' => $zaak->zgw_zaak_url,
    ]);

    fakeEligibleZaakResults([
        $this->zaaktype->zgw_zaaktype_url => [
            zgwZaakResult($zaak->zgw_zaak_url),
        ],
    ]);

    expect(app(EligibleZaakFinder::class)->find($this->municipality))->toBeEmpty();
});

test('zaken on a fully destroyed list can be listed again', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $list = DestructionList::factory()->create([
        'municipality_id' => $this->municipality->id,
        'status' => DestructionListStatus::Deleted,
    ]);
    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'zaak_id' => $zaak->id,
        'zgw_zaak_url' => $zaak->zgw_zaak_url,
    ]);

    fakeEligibleZaakResults([
        $this->zaaktype->zgw_zaaktype_url => [
            zgwZaakResult($zaak->zgw_zaak_url),
        ],
    ]);

    expect(app(EligibleZaakFinder::class)->find($this->municipality))->toHaveCount(1);
});
