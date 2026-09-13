<?php

declare(strict_types=1);

/**
 * A documents API can hand over the list of documents belonging to a zaak and
 * still not hand over an individual document from that list. Without containment
 * the first such document takes the whole zaak detail screen with it, for every
 * role.
 *
 * These tests pin the three halves of the containment: the screen survives and
 * keeps showing the documents it does have, it says out loud that something is
 * missing rather than showing a short list as if it were complete, and the
 * paths that need every document (mail attachments, queued jobs) still fail
 * loudly instead of quietly working with a gap.
 *
 * And they pin the distinction inside it. A document the API is not authorised
 * to hand over is answered with a 403 and refused on every call, so it gets a
 * message without a "try again later", no number on screen, a longer cache
 * window and a damped report. A server error or a timeout keeps all four of the
 * originals, because for that one waiting is the right advice.
 */

use App\Enums\Role;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
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
 * The body a ZGW API returns when it is not authorised to hand a resource over:
 * a machine code and free text. Nothing here may end up in a log line except the
 * code.
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
 * The body a ZGW API returns when it fails to produce a resource it would
 * otherwise hand over: the case that may pass on its own.
 *
 * @return array<string, mixed>
 */
function outageBody(): array
{
    return [
        'type' => 'https://zgw.example.com/ref/fouten/InternalServerError/',
        'code' => 'error',
        'title' => 'Er is een serverfout opgetreden.',
        'status' => 500,
        'detail' => '',
        'instance' => 'urn:uuid:00000000-0000-0000-0000-000000000001',
    ];
}

/**
 * A zaak whose document list resolves, with one entry per spec. A spec is one of
 * `['titel' => ...]` for a document that can be read, `['refused' => true]` for
 * one the API is not authorised to hand over, or `['unavailable' => true]` for
 * one it fails to produce.
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
        } elseif ($document['unavailable'] ?? false) {
            Http::fake([$docUrl => Http::response(outageBody(), 500)]);
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

/**
 * A zaak that reads through its own ZGW connection instead of the default one.
 * The connection is pointed at the same faked instance on purpose: the reads it
 * performs are then identical to those of a zaak on the default connection, so
 * the connection name is the only thing that differs.
 */
function zaakOnOwnZgwConnection(string $zaakUuid): Zaak
{
    $municipality = Municipality::factory()->create();

    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
        'zaken_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/',
        'catalogi_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/',
        'documenten_url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/',
        'besluiten_url' => ZgwHttpFake::$baseUrl.'/besluiten/api/v1/',
        'autorisaties_url' => ZgwHttpFake::$baseUrl.'/autorisaties/api/v1/',
        'notificaties_url' => ZgwHttpFake::$baseUrl.'/notificaties/api/v1/',
    ]);

    return Zaak::factory()->create([
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/'.$zaakUuid,
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => $municipality->id,
            'connection' => "gemeente_{$municipality->id}",
        ])->id,
    ]);
}

test('the documents tab keeps rendering when one document cannot be read', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['unavailable' => true],
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
        ['unavailable' => true],
        ['unavailable' => true],
    ]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('2 bestanden kunnen nu niet worden getoond');
});

test('a document the API is not authorised to hand over is reported without a promise that waiting helps', function () {
    // The authorisation is a setting on the other side of the connection and it
    // answers the same tomorrow, so "probeer het later opnieuw" sends the reader
    // waiting for something that is never going to arrive.
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
    ]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('Aanvraagformulier')
        ->assertSee('Niet beschikbaar via deze koppeling')
        ->assertDontSee('Probeer het later opnieuw')
        ->assertDontSee('kan nu niet worden getoond');
});

test('the notice for documents the API may not hand over carries no number', function () {
    // The count is not filtered by what this reader may see, so it would tell
    // them how many documents exist beyond their own visibility. There must be no
    // route by which it reaches the screen.
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
        ['refused' => true],
        ['refused' => true],
    ]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('Niet beschikbaar via deze koppeling')
        ->assertDontSee('3 bestanden')
        ->assertDontSee('kunnen nu niet worden getoond');
});

test('a zaak whose documents could none of them be read is not presented as a zaak without documents', function () {
    // The two empty states that would be untrue here: "the files are still
    // coming" (they are already there) and "not visible with your rights" (the
    // visibility rules are not what kept them off the screen).
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([['unavailable' => true]]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('De bestanden kunnen nu niet worden getoond')
        ->assertDontSee('Een ogenblik geduld')
        ->assertDontSee('niet zichtbaar met uw rechten');
});

test('a zaak whose documents the API may none of them hand over says so without a promise that waiting helps', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([['refused' => true]]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('De bestanden zijn niet beschikbaar via deze koppeling')
        ->assertDontSee('kunnen nu niet worden getoond')
        ->assertDontSee('Probeer het later opnieuw')
        ->assertDontSee('Een ogenblik geduld')
        ->assertDontSee('niet zichtbaar met uw rechten');
});

test('a mixed read says both things, and only the passing half is counted', function () {
    // One document refused by authorisation and one that merely failed is not an
    // edge case but the expected shape once a connection has both. Both notices
    // belong on screen, and the number may only describe the second.
    $this->actingAs(User::factory()->create(['role' => Role::Reviewer]));

    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
        ['unavailable' => true],
    ]);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSee('Eén bestand kan nu niet worden getoond')
        ->assertSee('Niet beschikbaar via deze koppeling')
        ->assertDontSee('2 bestanden kunnen nu niet worden getoond');
});

test('a document that could not be read is left out of the display set and counted', function () {
    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['unavailable' => true],
    ]);

    $set = $zaak->documentenForDisplay();

    expect($set->documenten)->toHaveCount(1)
        ->and($set->unavailableCount)->toBe(1)
        ->and($set->forbiddenCount)->toBe(0)
        ->and($set->hasUnavailable())->toBeTrue()
        ->and($set->hasForbidden())->toBeFalse()
        ->and($set->isIncomplete())->toBeTrue()
        ->and($set->totalCount())->toBe(2);
});

test('a document the API may not hand over is counted apart from one that merely failed', function () {
    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
    ]);

    $set = $zaak->documentenForDisplay();

    expect($set->documenten)->toHaveCount(1)
        ->and($set->forbiddenCount)->toBe(1)
        ->and($set->unavailableCount)->toBe(0)
        ->and($set->hasForbidden())->toBeTrue()
        ->and($set->hasUnavailable())->toBeFalse()
        ->and($set->isIncomplete())->toBeTrue()
        // A document that exists but may not be handed over still counts towards
        // what the zaak holds, or a screen would claim the zaak has no documents.
        ->and($set->totalCount())->toBe(2);
});

test('the documenten attribute still fails when a document cannot be read', function () {
    // Mail attachments and queued jobs read this attribute. An attachment list
    // that quietly drops a document is worse than a job that fails, so the
    // containment must not reach this path, whichever of the two reasons it was.
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
    // refresh would be a fresh round of calls for as long as the failure lasts.
    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['unavailable' => true],
    ]);

    $zaak->documentenForDisplay();
    $callsAfterFirstRead = count(Http::recorded());

    $second = $zaak->documentenForDisplay();

    expect(count(Http::recorded()))->toBe($callsAfterFirstRead)
        ->and($second->unavailableCount)->toBe(1)
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
    // The other half of the caching rule for a failure that may pass: brief, so
    // the notice cannot outlive the problem by more than the window.
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $docUrl = ZgwHttpFake::documentUrl('1');

    Http::fake([
        $docUrl => Http::sequence()
            ->push(outageBody(), 500)
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

    expect($zaak->documentenForDisplay()->unavailableCount)->toBe(1);

    $this->travel(61)->seconds();

    $recovered = $zaak->documentenForDisplay();

    expect($recovered->unavailableCount)->toBe(0)
        ->and($recovered->documenten)->toHaveCount(1);
});

test('a read the API refused on authorisation is cached for longer than one that merely failed', function () {
    // Repeating a call that is refused by design, every minute, for as long as a
    // screen is open, buys nothing. The window is therefore longer than the one
    // above, and the cost of that is the delay before a widened authorisation
    // shows up.
    $zaak = zaakWithDocumentReads([
        ['titel' => 'Aanvraagformulier'],
        ['refused' => true],
    ]);

    $zaak->documentenForDisplay();
    $callsAfterFirstRead = count(Http::recorded());

    $this->travel(61)->seconds();

    expect($zaak->documentenForDisplay()->forbiddenCount)->toBe(1)
        ->and(count(Http::recorded()))->toBe($callsAfterFirstRead);

    $this->travel(900)->seconds();

    $zaak->documentenForDisplay();

    expect(count(Http::recorded()))->toBeGreaterThan($callsAfterFirstRead);
});

test('a mixed read keeps the short cache window, because half of it still has to recover', function () {
    $zaak = zaakWithDocumentReads([
        ['refused' => true],
        ['unavailable' => true],
    ]);

    $zaak->documentenForDisplay();
    $callsAfterFirstRead = count(Http::recorded());

    $this->travel(61)->seconds();

    $zaak->documentenForDisplay();

    expect(count(Http::recorded()))->toBeGreaterThan($callsAfterFirstRead);
});

test('a document that merely failed is reported on every read, so the degradation stays visible', function () {
    // A log line does not replace the error report the failure used to produce:
    // the log stack has no reporting channel in it, and the report is what
    // carries the API response as context.
    Exceptions::fake();

    $zaak = zaakWithDocumentReads([['unavailable' => true]]);

    $zaak->documentenForDisplay();

    Exceptions::assertReported(ApiRequestException::class);
    Exceptions::assertReportedCount(1);

    $this->travel(61)->seconds();

    $zaak->documentenForDisplay();

    Exceptions::assertReportedCount(2);
});

test('a document the API may not hand over is reported once and then damped', function () {
    // Working as configured is not an error report. It is not dropped either: a
    // narrowed authorisation has to be noticeable, so the first read of the day
    // reports and the reads after it do not.
    Exceptions::fake();

    $zaak = zaakWithDocumentReads([['refused' => true]]);

    $zaak->documentenForDisplay();

    Exceptions::assertReported(ApiRequestException::class);
    Exceptions::assertReportedCount(1);

    $this->travel(901)->seconds();

    $zaak->documentenForDisplay();

    Exceptions::assertReportedCount(1);
});

test('the damper covers every zaak on one ZGW connection and stops at the next connection', function () {
    // An authorisation is configured on the connection, so the answer is the same
    // for every zaak that connection serves: one report describes the setting and
    // a report per zaak would only multiply it by how many zaken happen to hold
    // such a document. A second connection is a second setting, so it has to be
    // able to report for itself; damping globally would let one connection
    // silence the rest.
    Exceptions::fake();

    $first = zaakWithDocumentReads([['refused' => true]]);
    $first->documentenForDisplay();

    Exceptions::assertReported(ApiRequestException::class);
    Exceptions::assertReportedCount(1);

    // A second zaak on the same connection, reading the same refused document
    // through the list endpoint the fake answers for any zaak.
    $second = Zaak::factory()->create([
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/2',
        'zaaktype_id' => $first->zaaktype_id,
    ]);
    $second->documentenForDisplay();

    expect($second->zgwConnectionName())->toBe($first->zgwConnectionName());

    Exceptions::assertReportedCount(1);

    $onOwnConnection = zaakOnOwnZgwConnection('3');
    $onOwnConnection->documentenForDisplay();

    expect($onOwnConnection->zgwConnectionName())->not->toBe($first->zgwConnectionName());

    Exceptions::assertReportedCount(2);
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
