<?php

declare(strict_types=1);

/**
 * The besluiten half of the same gap. A besluit's documents are fetched one by
 * one on exactly the endpoint the zaak documents use, with the same absence of
 * containment, and the besluiten tab resolves that during the page render. One
 * besluit document the API would not hand over therefore used to take the whole
 * zaak detail screen with it, just like a zaak document did.
 *
 * It differs in one way that matters: a besluit is only shown once it carries an
 * established document, so a missing document can make the besluit disappear
 * altogether rather than merely shorten its file list. These tests pin that the
 * reader is told about that instead of being shown a screen without besluiten,
 * and that the same 403-versus-everything-else distinction the documents tab
 * makes is made here too.
 */

use App\Livewire\Zaken\BesluitenInfolist;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Exceptions\ApiRequestException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
});

/**
 * A zaak with one besluit whose documents are read one by one. Each spec is
 * one of `['titel' => ...]` for a document that can be read,
 * `['refused' => true]` for one the API is not authorised to hand over, or
 * `['unavailable' => true]` for one it fails to produce.
 *
 * @param  list<array<string, mixed>>  $documents
 */
function zaakWithBesluitDocumentReads(array $documents): Zaak
{
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $besluitUrl = ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1';
    $besluittypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/besluittypen/1';

    $links = [];

    foreach ($documents as $index => $document) {
        $uuid = 'besluit-doc-'.($index + 1);
        $docUrl = ZgwHttpFake::documentUrl($uuid);

        if ($document['refused'] ?? false) {
            Http::fake([$docUrl => Http::response(besluitRefusalBody(), 403)]);
        } elseif ($document['unavailable'] ?? false) {
            Http::fake([$docUrl => Http::response(besluitOutageBody(), 500)]);
        } else {
            ZgwHttpFake::fakeSingleDocument($uuid, ['titel' => $document['titel'] ?? 'Besluitdocument '.$uuid]);
        }

        $links[] = [
            'url' => ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten/'.($index + 1),
            'besluit' => $besluitUrl,
            'informatieobject' => $docUrl,
        ];
    }

    Http::fake([
        $besluittypeUrl => Http::response([
            'url' => $besluittypeUrl,
            'omschrijving' => 'Vergunning',
            'omschrijvingGeneriek' => 'Vergunning',
            'zaaktypen' => [ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1'],
            'informatieobjecttypen' => [ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1'],
            'toelichting' => '',
        ], 200),
        ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluitinformatieobjecten*' => Http::response(ZgwHttpFake::envelope($links), 200),
        ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten*' => Http::response(ZgwHttpFake::envelope([[
            'url' => $besluitUrl,
            'identificatie' => 'BESLUIT-1',
            'besluittype' => $besluittypeUrl,
            'zaak' => $zaakUrl,
            'datum' => now()->format('Y-m-d'),
            'ingangsdatum' => now()->format('Y-m-d'),
            'toelichting' => 'Toelichting',
            'verzenddatum' => now()->format('Y-m-d'),
        ]]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([]), 200),
    ]);

    return Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => Zaaktype::factory()->for(Municipality::factory())->create([
            'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ])->id,
    ]);
}

/**
 * The answer when the API is not authorised to hand the document over.
 *
 * @return array<string, mixed>
 */
function besluitRefusalBody(): array
{
    return [
        'code' => 'permission_denied',
        'title' => 'Toestemming geweigerd.',
        'status' => 403,
        'detail' => 'Dit besluitdocument hoort bij een dossier waar deze koppeling niet bij mag.',
    ];
}

/**
 * The answer when the API fails to produce a document it would otherwise hand
 * over: the case that may pass on its own.
 *
 * @return array<string, mixed>
 */
function besluitOutageBody(): array
{
    return [
        'code' => 'error',
        'title' => 'Er is een serverfout opgetreden.',
        'status' => 500,
        'detail' => '',
    ];
}

test('the besluiten list keeps rendering when one besluit document cannot be read', function () {
    $zaak = zaakWithBesluitDocumentReads([
        ['titel' => 'Vergunning'],
        ['unavailable' => true],
    ]);

    livewire(BesluitenInfolist::class, ['zaak' => $zaak])
        ->assertSee('Vergunning')
        ->assertSee('Eén bestand bij een besluit kan nu niet worden getoond');
});

test('a besluit document the API may not hand over is reported without a promise that waiting helps', function () {
    $zaak = zaakWithBesluitDocumentReads([
        ['titel' => 'Vergunning'],
        ['refused' => true],
    ]);

    livewire(BesluitenInfolist::class, ['zaak' => $zaak])
        ->assertSee('Vergunning')
        ->assertSee('Niet beschikbaar via deze koppeling')
        ->assertDontSee('Probeer het later opnieuw')
        ->assertDontSee('kan nu niet worden getoond');
});

test('the besluiten notice for documents the API may not hand over carries no number', function () {
    $zaak = zaakWithBesluitDocumentReads([
        ['refused' => true],
        ['refused' => true],
    ]);

    livewire(BesluitenInfolist::class, ['zaak' => $zaak])
        ->assertSee('Niet beschikbaar via deze koppeling')
        ->assertDontSee('2 bestanden')
        ->assertDontSee('kunnen nu niet worden getoond');
});

test('a besluit that fell away because its only document is missing is not left unmentioned', function () {
    // A besluit is only shown once it carries an established document, so this
    // besluit is gone from the list. Without the notice the screen would simply
    // show no besluiten, which is the untrue empty state.
    $zaak = zaakWithBesluitDocumentReads([['unavailable' => true]]);

    $set = $zaak->besluitenForDisplay();

    expect($set->besluiten)->toHaveCount(0)
        ->and($set->unavailableDocumentCount)->toBe(1)
        ->and($set->forbiddenDocumentCount)->toBe(0)
        ->and($set->hasSomethingToShow())->toBeTrue();

    livewire(BesluitenInfolist::class, ['zaak' => $zaak])
        ->assertSee('Eén bestand bij een besluit kan nu niet worden getoond');
});

test('a besluit that fell away because the API may not hand its document over is not left unmentioned either', function () {
    // The tab has to stay visible for this reason too, or the reader never learns
    // the besluit exists at all.
    $zaak = zaakWithBesluitDocumentReads([['refused' => true]]);

    $set = $zaak->besluitenForDisplay();

    expect($set->besluiten)->toHaveCount(0)
        ->and($set->forbiddenDocumentCount)->toBe(1)
        ->and($set->unavailableDocumentCount)->toBe(0)
        ->and($set->hasForbiddenDocuments())->toBeTrue()
        ->and($set->hasUnavailableDocuments())->toBeFalse()
        ->and($set->hasSomethingToShow())->toBeTrue();

    livewire(BesluitenInfolist::class, ['zaak' => $zaak])
        ->assertSee('Niet beschikbaar via deze koppeling');
});

test('the readable besluit documents are still listed', function () {
    $zaak = zaakWithBesluitDocumentReads([
        ['titel' => 'Vergunning'],
        ['refused' => true],
    ]);

    $set = $zaak->besluitenForDisplay();

    expect($set->besluiten)->toHaveCount(1)
        ->and($set->besluiten->first()->besluitDocumenten)->toHaveCount(1)
        ->and($set->forbiddenDocumentCount)->toBe(1);
});

test('the besluiten attribute still fails when a besluit document cannot be read', function () {
    // Same boundary as the documenten attribute: whatever needs every besluit
    // document must not be handed a shorter list without knowing.
    $zaak = zaakWithBesluitDocumentReads([
        ['titel' => 'Vergunning'],
        ['refused' => true],
    ]);

    expect(fn () => $zaak->besluiten)->toThrow(ApiRequestException::class);
});

test('an incomplete besluit read is cached and kept away from the strict path', function () {
    $zaak = zaakWithBesluitDocumentReads([
        ['titel' => 'Vergunning'],
        ['refused' => true],
    ]);

    $zaak->besluitenForDisplay();
    $callsAfterFirstRead = count(Http::recorded());

    expect($zaak->besluitenForDisplay()->forbiddenDocumentCount)->toBe(1)
        ->and(count(Http::recorded()))->toBe($callsAfterFirstRead)
        ->and(fn () => $zaak->besluiten)->toThrow(ApiRequestException::class);
});
