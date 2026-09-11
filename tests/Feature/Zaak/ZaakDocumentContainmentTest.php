<?php

declare(strict_types=1);

/**
 * A documents API can hand over the list of documents belonging to a zaak and
 * still refuse an individual document from that list. Without containment the
 * first refusal takes the whole zaak detail screen with it, for every role.
 *
 * These tests pin the three halves of the containment: the screen survives and
 * keeps showing the documents it does have, it says out loud that something is
 * missing rather than showing a short list as if it were complete, and the
 * paths that need every document (mail attachments, queued jobs) still fail
 * loudly instead of quietly working with a gap.
 */

use App\Enums\Role;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\DisallowedHostException;

use function Pest\Livewire\livewire;

/**
 * The body a ZGW API returns when it refuses a resource: a machine code and
 * free text. Nothing here may end up in a log line except the code.
 *
 * @return array<string, mixed>
 */
function refusalBody(string $code = 'permission_denied'): array
{
    return [
        'type' => 'https://zgw.example.com/ref/fouten/PermissionDenied/',
        'code' => $code,
        'title' => 'Toestemming geweigerd.',
        'status' => 403,
        'detail' => 'Dit document hoort bij een dossier waar deze koppeling niet bij mag.',
        'instance' => 'urn:uuid:00000000-0000-0000-0000-000000000000',
    ];
}

/**
 * A zaak whose document list resolves, with one entry per spec. A spec is
 * either `['titel' => ...]` for a document that can be read, or
 * `['refused' => true]` for one the documents API turns down.
 *
 * @param  list<array<string, mixed>>  $documents
 */
function zaakWithDocumentReads(array $documents): Zaak
{
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $links = [];

    foreach ($documents as $index => $document) {
        $uuid = (string) ($index + 1);
        $docUrl = ZgwHttpFake::documentUrl($uuid);

        if ($document['refused'] ?? false) {
            Http::fake([$docUrl => Http::response(refusalBody(), 403)]);
        } else {
            ZgwHttpFake::fakeSingleDocument($uuid, ['titel' => $document['titel'] ?? 'Document '.$uuid]);
        }

        $links[] = [
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/'.$uuid,
            'zaak' => $zaakUrl,
            'informatieobject' => $docUrl,
        ];
    }

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope($links), 200),
        // The table renders a document-type column, which resolves the zaaktype's
        // document types. Not what these tests are about, so it stays empty.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([]), 200),
    ]);

    return Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => Zaaktype::factory()->for(Municipality::factory())->create()->id,
    ]);
}

test('the documents tab keeps rendering when one document cannot be read', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
        ['titel' => 'Plattegrond'],
    ]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('Aanvraagformulier')
        ->assertSee('Plattegrond')
        ->assertSee('Eén bestand kan nu niet worden getoond');
});

test('the notice counts every document that could not be read', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
        ['refused' => true],
    ]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('2 bestanden kunnen nu niet worden getoond');
});

test('a zaak whose documents are all refused is not presented as a zaak without documents', function () {
    // The two empty states that would be untrue here: "the files are still
    // coming" (they are already there) and "not visible with your rights"
    // (rights are not what went wrong).
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([['refused' => true]]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('De bestanden kunnen nu niet worden getoond')
        ->assertDontSee('Een ogenblik geduld')
        ->assertDontSee('niet zichtbaar met uw rechten');
});

test('a refused document is left out of the display set and counted', function () {
    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
    ]);

    $set = $zaak->documentenForDisplay();

    expect($set->documenten)->toHaveCount(1)
        ->and($set->unreadableCount)->toBe(1)
        ->and($set->isIncomplete())->toBeTrue()
        ->and($set->totalCount())->toBe(2);
});

test('the documenten attribute still fails when a document cannot be read', function () {
    // Mail attachments and queued jobs read this attribute. An attachment list
    // that quietly drops a document is worse than a job that fails, so the
    // containment must not reach this path.
    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
    ]);

    expect(fn () => $zaak->documenten)->toThrow(ApiRequestException::class);
});

test('a skipped document is logged with its url, status and error code, and without the response body', function () {
    Log::spy();

    $zaak = zaakWithDocumentReads([['refused' => true]]);

    $zaak->documentenForDisplay();

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context) use ($zaak): bool {
            if (! str_contains($message, 'could not be read')) {
                return false;
            }

            expect(array_keys($context))->toBe(['zaak_id', 'connection', 'kind', 'url', 'status', 'code', 'exception'])
                ->and($context['zaak_id'])->toBe($zaak->id)
                ->and($context['kind'])->toBe('document')
                ->and($context['url'])->toBe(ZgwHttpFake::documentUrl('1'))
                ->and($context['status'])->toBe(403)
                ->and($context['code'])->toBe('permission_denied')
                // The body of a documents API answer can carry the document's own
                // metadata, so nothing from it may reach the log except the code.
                ->and(json_encode($context))->not->toContain('Toestemming geweigerd')
                ->and(json_encode($context))->not->toContain('deze koppeling niet bij mag');

            return true;
        })
        ->once();
});

test('an incomplete read is cached briefly so a polling screen does not keep calling the API', function () {
    // The documents tab refreshes itself every few seconds. Without a cache each
    // refresh would be a fresh round of calls for as long as the refusal lasts,
    // and a refusal can be a permission setting rather than a passing outage.
    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
    ]);

    $zaak->documentenForDisplay();
    $callsAfterFirstRead = count(Http::recorded());

    $second = $zaak->documentenForDisplay();

    expect(count(Http::recorded()))->toBe($callsAfterFirstRead)
        ->and($second->unreadableCount)->toBe(1)
        ->and($second->documenten)->toHaveCount(1);
});

test('a cached incomplete read is never handed to a caller that needs every document', function () {
    // The cached gap is stored as the set itself, not as a plain collection, so
    // the strict path reads straight past it and fails on the API instead of
    // quietly working with a short list.
    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
    ]);

    $zaak->documentenForDisplay();

    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeTrue()
        ->and(fn () => $zaak->documenten)->toThrow(ApiRequestException::class);
});

test('a complete read is cached as before', function () {
    $zaak = zaakWithDocumentReads([['titel' => 'Aanvraagformulier']]);

    $zaak->documentenForDisplay();

    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeTrue();
});

test('the list is whole again once the short cache window has passed and the document can be read', function () {
    // The other half of the caching rule: brief, so the notice cannot outlive
    // the problem by more than the window.
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $docUrl = ZgwHttpFake::documentUrl('1');

    Http::fake([
        $docUrl => Http::sequence()
            ->push(refusalBody(), 403)
            ->push(ZgwHttpFake::documentBody('1', ['titel' => 'Aanvraagformulier']), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([[
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
            'zaak' => $zaakUrl,
            'informatieobject' => $docUrl,
        ]]), 200),
    ]);

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => Zaaktype::factory()->for(Municipality::factory())->create()->id,
    ]);

    expect($zaak->documentenForDisplay()->unreadableCount)->toBe(1);

    $this->travel(61)->seconds();

    $recovered = $zaak->documentenForDisplay();

    expect($recovered->unreadableCount)->toBe(0)
        ->and($recovered->documenten)->toHaveCount(1);
});

test('a skipped document is reported, so the degradation stays visible in error reporting', function () {
    // A log line does not replace the error report the failure used to produce:
    // the log stack has no reporting channel in it, and the report is what
    // carries the API response as context.
    Exceptions::fake();

    $zaak = zaakWithDocumentReads([['refused' => true]]);

    $zaak->documentenForDisplay();

    Exceptions::assertReported(ApiRequestException::class);
});

test('a document url on an origin the connection does not trust stays a hard failure', function () {
    // Not "a document we could not read" but "a url we refuse to call". The
    // allowlist guard exists to raise that loudly, and softening it into a
    // notice on screen would take the signal away.
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $foreignUrl = 'https://not-on-the-allowlist.example.net/documenten/api/v1/enkelvoudiginformatieobject/1';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([[
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
            'zaak' => $zaakUrl,
            'informatieobject' => $foreignUrl,
        ]]), 200),
    ]);

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => Zaaktype::factory()->for(Municipality::factory())->create()->id,
    ]);

    expect(fn () => $zaak->documentenForDisplay())->toThrow(DisallowedHostException::class);
});
