<?php

namespace Tests\Fakes;

use App\Enums\DocumentVertrouwelijkheden;
use Illuminate\Support\Facades\Http;

class ZgwHttpFake
{
    public static $baseUrl = 'https://zgw.example.com';

    public static function fakeSingleZaak(string $uuid = '1', array $data = []): string
    {
        $url = self::$baseUrl.'/zaken/api/v1/zaken/'.$uuid;

        $data = array_merge([
            'url' => $url,
            'identificatie' => 'ZAAK-123',
            'omschrijving' => 'Test zaak',
            'zaaktype' => self::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
            'status' => self::$baseUrl.'/zaken/api/v1/statussen/1',
            'startdatum' => now()->toIso8601String(),
            'einddatum' => null,
            'betrokkene' => [],
            'object' => self::$baseUrl.'/zaken/api/v1/zaakobjecten/1',
            'zaakobject' => self::$baseUrl.'/zaken/api/v1/zaakobjecten/1',
            'resultaat' => null,
            'bronorganisatie' => '123',
            'doelorganisatie' => null,
            'toelichting' => 'This is a test zaak',
        ], $data);

        Http::fake([
            $url => Http::response($data, 200),
        ]);

        return $url;
    }

    public static function fakeSingleDocument(string $uuid = '1', array $data = []): string
    {
        $url = self::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$uuid;

        $data = array_merge([
            'url' => $url,
            'uuid' => $uuid,
            'identificatie' => 'DOC-123',
            'titel' => 'Test Document',
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk,
            'auteur' => 'Test',
            'versie' => 1,
            'bestandsnaam' => 'test_document.pdf',
            'inhoud' => '123',
            'beschrijving' => 'This is a test document',
            'informatieobjecttype' => self::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
            'formaat' => 'application/pdf',
            'locked' => false,
            'bestandsgrootte' => 2048,
            'creatiedatum' => now()->toIso8601String(),
            'wijzigingsdatum' => now()->toIso8601String(),
            'zaak' => self::$baseUrl.'/zaken/api/v1/zaken/1',
            'bestandslocatie' => self::$baseUrl.'/files/doc-123.pdf',
        ], $data);

        Http::fake([
            $url => Http::response($data, 200),
        ]);

        return $url;
    }

    public static function fakeZaakinformatieobjecten()
    {
        $url = self::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten?*';

        $data = [
            [
                'url' => self::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
                'zaak' => self::$baseUrl.'/zaken/api/v1/zaken/1',
                'informatieobject' => self::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/1',
            ],
            [
                'url' => self::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/2',
                'zaak' => self::$baseUrl.'/zaken/api/v1/zaken/1',
                'informatieobject' => self::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/2',
            ],
        ];

        Http::fake([
            $url => Http::response($data, 200),
        ]);

        return $url;
    }
}
