<?php

namespace Tests\Fakes;

use App\Enums\DocumentVertrouwelijkheden;
use Illuminate\Support\Facades\Http;

class ZgwHttpFake
{
    public static $baseUrl = 'https://zgw.example.com';

    /**
     * Wrap a list of resources in the ZGW pagination envelope.
     *
     * The new woweb/laravel-zgw-client reads $response['results'] strictly, so list
     * fakes must return the envelope. The legacy woweb/openzaak package is tolerant of
     * both shapes, so this stays compatible while call sites are being migrated.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    public static function envelope(array $results): array
    {
        return [
            'count' => count($results),
            'next' => null,
            'previous' => null,
            'results' => $results,
        ];
    }

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
            'registratiedatum' => now()->toIso8601String(),
            'einddatum' => null,
            'einddatumGepland' => null,
            'uiterlijkeEinddatumAfdoening' => null,
            'zaakgeometrie' => null,
            'betrokkene' => [],
            'object' => self::$baseUrl.'/zaken/api/v1/zaakobjecten/1',
            'zaakobject' => self::$baseUrl.'/zaken/api/v1/zaakobjecten/1',
            'resultaat' => null,
            'bronorganisatie' => '123',
            'doelorganisatie' => null,
            'toelichting' => 'This is a test zaak',
        ], $data);

        Http::fake([
            $url.'*' => Http::response($data, 200),
        ]);

        return $url;
    }

    public static function documentUrl(string $uuid = '1'): string
    {
        return self::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$uuid;
    }

    public static function fakeSingleDocument(string $uuid = '1', array $data = []): string
    {
        $url = self::documentUrl($uuid);

        Http::fake([
            $url => Http::response(self::documentBody($uuid, $data), 200),
        ]);

        return $url;
    }

    /**
     * The body of a single document, without registering a stub for it, so a
     * test can hand the same body to a stub of its own (a sequence, say).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function documentBody(string $uuid = '1', array $data = []): array
    {
        $url = self::documentUrl($uuid);

        return array_merge([
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
    }

    /**
     * Fake the besluitinformatieobjecten list endpoint (links between a besluit
     * and its documents). Defaults to empty, so a document is treated as not
     * belonging to a besluit.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    public static function fakeBesluitinformatieobjecten(array $results = [])
    {
        $url = self::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten';

        Http::fake([
            $url.'*' => Http::response(self::envelope($results), 200),
        ]);

        return $url;
    }

    public static function fakeZaakinformatieobjecten()
    {
        $url = self::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten';

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
            $url.'*' => Http::response(self::envelope($data), 200),
        ]);

        return $url;
    }

    public static function fakeSingleZaaktype()
    {
        $url = self::$baseUrl.'/catalogi/api/v1/zaaktypen/1';

        $data = [
            'url' => $url,
            'uuid' => '1',
            'identificatie' => 'TEST-ZAAKTYPE',
            'omschrijving' => 'Evenementenvergunning gemeente Testdorp',
            'omschrijvingGeneriek' => '',
            'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
            'doel' => 'Verlenen evenementenvergunning',
            'aanleiding' => 'Aanvraag via EventLoket',
            'toelichting' => '',
            'indicatieInternOfExtern' => 'extern',
            'handelingInitiator' => 'aanvragen',
            'onderwerp' => 'Evenementenvergunning',
            'handelingBehandelaar' => 'behandelen',
            'doorlooptijd' => 'P56D',
            'servicenorm' => 'P56D',
            'opschortingEnAanhoudingMogelijk' => true,
            'verlengingMogelijk' => true,
            'verlengingstermijn' => 'P56D',
            'trefwoorden' => [],
            'publicatieIndicatie' => false,
            'publicatietekst' => '',
            'verantwoordingsrelatie' => [],
            'productenOfDiensten' => [],
            'concept' => false,
            'verantwoordelijke' => 'APV',
            'beginGeldigheid' => '2025-07-10',
            'eindeGeldigheid' => null,
            'versiedatum' => '2025-07-10',
            'beginObject' => '2025-07-10',
            'eindeObject' => null,
            'catalogus' => self::$baseUrl.'/catalogi/api/v1/catalogi/1',
            'doorlooptijd' => '',
        ];

        Http::fake([
            $url => Http::response($data, 200),
        ]);

        return $url;
    }

    public static function fakeResultaatTypen()
    {
        $url = self::$baseUrl.'/catalogi/api/v1/resultaattypen';

        $data = [
            [
                'url' => self::$baseUrl.'/catalogi/api/v1/resultaattypen/1',
                'zaaktype' => self::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'omschrijvingGeneriek' => 'Afgehandeld',
            ],
            [
                'url' => self::$baseUrl.'/catalogi/api/v1/resultaattypen/2',
                'zaaktype' => self::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'omschrijvingGeneriek' => 'Ingetrokken',
            ],
        ];

        Http::fake([
            $url.'*' => Http::response(self::envelope($data), 200),
        ]);

        return $url;
    }

    public static function fakeStatustypen()
    {
        $url = self::$baseUrl.'/catalogi/api/v1/statustypen';

        $data = [
            [
                'url' => self::$baseUrl.'/catalogi/api/v1/statustypen/1',
                'zaaktype' => self::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'omschrijving' => 'Ontvangen',
                'volgnummer' => 1,
                'isEindstatus' => false,
            ],
            [
                'url' => self::$baseUrl.'/catalogi/api/v1/statustypen/2',
                'zaaktype' => self::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'omschrijving' => 'In behandeling',
                'volgnummer' => 2,
                'isEindstatus' => false,
            ],
            [
                'url' => self::$baseUrl.'/catalogi/api/v1/statustypen/3',
                'zaaktype' => self::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'omschrijving' => 'Afgehandeld',
                'volgnummer' => 3,
                'isEindstatus' => true,
            ],
        ];

        Http::fake([
            $url.'*' => Http::response(self::envelope($data), 200),
        ]);

        return $url;
    }

    public static function wildcardFake()
    {
        Http::fake([
            self::$baseUrl.'*' => Http::response([], 200),
        ]);
    }
}
