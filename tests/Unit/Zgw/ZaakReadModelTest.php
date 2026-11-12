<?php

use App\Services\Zgw\ZaakReadModel;
use Illuminate\Support\Facades\Config;

function zaakWithZaakobjecten(array $objects): ZaakReadModel
{
    return ZaakReadModel::fromArray([
        'url' => 'https://zgw.example.com/zaken/api/v1/zaken/1',
        'uuid' => '1',
        'identificatie' => 'ZAAK-1',
        'zaaktype' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        '_expand' => [
            'zaakobjecten' => array_map(fn (string $object): array => ['object' => $object], $objects),
        ],
    ]);
}

beforeEach(function () {
    Config::set('openzaak.objectsapi.url', 'https://objects.example.com/');
});

it('recognises a zaakobject on the objects api host', function () {
    $url = 'https://objects.example.com/api/v2/objects/abc';

    expect(zaakWithZaakobjecten([$url])->data_object_url)->toBe($url);
});

it('does not recognise a zaakobject that only mentions the objects api in its query string', function () {
    // A substring match would have taken this one, and the delete that follows
    // would then have sent our Objects API credential to another host.
    $url = 'https://aanvaller.example.org/api/v2/objects/1?callback=https://objects.example.com/';

    expect(zaakWithZaakobjecten([$url])->data_object_url)->toBeNull();
});

it('does not recognise a host that merely starts with the objects api host', function () {
    expect(zaakWithZaakobjecten(['https://objects.example.com.aanvaller.org/api/v2/objects/1'])->data_object_url)
        ->toBeNull();
});

it('picks the zaakobject on the objects api host from among others', function () {
    $url = 'https://objects.example.com/api/v2/objects/abc';

    expect(zaakWithZaakobjecten([
        'https://zgw.example.com/zaken/api/v1/zaakobjecten/1',
        $url,
    ])->data_object_url)->toBe($url);
});

it('has no data object when the objects api is not configured', function () {
    Config::set('openzaak.objectsapi.url', '');

    expect(zaakWithZaakobjecten(['https://objects.example.com/api/v2/objects/abc'])->data_object_url)
        ->toBeNull();
});
