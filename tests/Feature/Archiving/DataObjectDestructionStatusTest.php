<?php

use App\Enums\DataObjectDestructionStatus;
use App\Enums\DestructionItemStatus;
use App\Enums\DestructionReportType;
use App\Jobs\Archiving\ZaakDestroyNotificationReceived;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use App\Models\Archiving\DestructionReport;
use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Archiving\EventloketDataDestroyer;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\ZgwHttpFake;

/**
 * The Eventloket data report states per zaak what happened to the form
 * submission object, so a refused or failed delete is never reported as a
 * removal. The local cleanup goes ahead in every case.
 */
beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');

    Config::set('openzaak.objectsapi.url', 'https://objects.example.com/');

    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
});

function zaakWithDataObject(Zaaktype $zaaktype, string $suffix, ?string $dataObjectUrl): Zaak
{
    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/'.$suffix,
        'data_object_url' => $dataObjectUrl,
    ]);
}

/**
 * The zaak is gone at the source; the Objects API answers with $objectsStatus.
 */
function fakeZaakGoneAndObjectsApi(int $objectsStatus): void
{
    Http::fake([
        // Followed, a redirect would become a GET that answers 200.
        'objects.example.com/api/v2/objects/moved' => Http::response(['url' => 'moved'], 200),
        'objects.example.com/*' => Http::response(
            $objectsStatus === 204 || $objectsStatus === 301 ? null : ['detail' => 'x'],
            $objectsStatus,
            $objectsStatus === 301 ? ['Location' => 'https://objects.example.com/api/v2/objects/moved'] : [],
        ),
        '*' => Http::response(['detail' => 'not found'], 404),
    ]);
}

function receiveDestroyNotification(Zaak $zaak): void
{
    new ZaakDestroyNotificationReceived(new OpenNotification(
        actie: 'destroy',
        kanaal: 'zaken',
        resource: 'zaak',
        hoofdObject: (string) $zaak->zgw_zaak_url,
        resourceUrl: (string) $zaak->zgw_zaak_url,
        aanmaakdatum: now()->toIso8601String(),
        senderConnection: 'main',
    ))->handle(app(EventloketDataDestroyer::class));
}

dataset('data object outcomes', [
    'deleted' => [204, 'https://objects.example.com/api/v2/objects/a', DataObjectDestructionStatus::Deleted],
    'already gone' => [404, 'https://objects.example.com/api/v2/objects/a', DataObjectDestructionStatus::Deleted],
    'not present' => [204, null, DataObjectDestructionStatus::NotPresent],
    'refused' => [204, 'https://elsewhere.example.org/api/v2/objects/a', DataObjectDestructionStatus::Refused],
    'failed' => [500, 'https://objects.example.com/api/v2/objects/a', DataObjectDestructionStatus::Failed],
    'redirected' => [301, 'https://objects.example.com/api/v2/objects/a', DataObjectDestructionStatus::Failed],
]);

test('the outcome for the form submission object is recorded on the one log row', function (int $objectsStatus, ?string $dataObjectUrl, DataObjectDestructionStatus $expected) {
    fakeZaakGoneAndObjectsApi($objectsStatus);

    $zaak = zaakWithDataObject($this->zaaktype, 'z1', $dataObjectUrl);

    receiveDestroyNotification($zaak);

    // The local cleanup is never held back by the object.
    expect(Zaak::withTrashed()->find($zaak->id))->toBeNull()
        ->and(ZaakDestructionLog::count())->toBe(1)
        ->and(ZaakDestructionLog::sole()->data_object_status)->toBe($expected);
})->with('data object outcomes');

test('a refused delete sends nothing to the other host', function () {
    fakeZaakGoneAndObjectsApi(204);

    receiveDestroyNotification(zaakWithDataObject($this->zaaktype, 'z1', 'https://elsewhere.example.org/api/v2/objects/a'));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'elsewhere.example.org'));
});

test('the report shows per zaak whether the form submission object was removed', function () {
    fakeZaakGoneAndObjectsApi(500);

    receiveDestroyNotification(zaakWithDataObject($this->zaaktype, 'failed', 'https://objects.example.com/api/v2/objects/a'));
    receiveDestroyNotification(zaakWithDataObject($this->zaaktype, 'refused', 'https://elsewhere.example.org/api/v2/objects/b'));
    receiveDestroyNotification(zaakWithDataObject($this->zaaktype, 'none', null));

    ZaakDestructionLog::query()->update(['destroyed_at' => now()->subDay()]);

    $this->artisan('archiving:report-eventloket-destructions')->assertSuccessful();

    $report = DestructionReport::sole();

    expect(collect($report->items)->pluck('data_object_status')->sort()->values()->all())
        ->toBe(['failed', 'not_present', 'refused']);

    $html = view('pdf.destruction-report', ['report' => $report])->render();

    expect($html)->toContain('Formulierinzending-object')
        ->toContain('niet verwijderd (fout)')
        ->toContain('niet verwijderd (geweigerd)')
        ->toContain('niet aanwezig');
});

test('a report written before the status was recorded renders without it', function () {
    $report = DestructionReport::factory()->create([
        'municipality_id' => $this->municipality->id,
        'type' => DestructionReportType::EventloketData,
        'items' => [[
            'zaaknummer' => 'ZAAK-1001',
            'zaaktype' => 'Evenementenvergunning',
            'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/old',
            'zgw_connection' => 'main',
            'destroyed_at' => now()->subWeek()->toIso8601String(),
        ]],
    ]);

    $html = view('pdf.destruction-report', ['report' => $report])->render();

    expect($html)->toContain('ZAAK-1001')
        ->not->toContain('verwijderd (');
});

test('the safety net records a refused delete the same way', function () {
    fakeZaakGoneAndObjectsApi(204);

    $zaak = zaakWithDataObject($this->zaaktype, 'z1', 'https://elsewhere.example.org/api/v2/objects/a');

    $list = DestructionList::factory()->create(['municipality_id' => $this->municipality->id]);

    DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'zaak_id' => $zaak->id,
        'zgw_zaak_url' => $zaak->zgw_zaak_url,
        'zaaknummer' => $zaak->public_id,
        'status' => DestructionItemStatus::Deleted,
        'destroyed_at' => now()->subDay(),
    ]);

    $this->artisan('archiving:reconcile-destroyed-zaken')->assertSuccessful();

    expect(Zaak::withTrashed()->find($zaak->id))->toBeNull()
        ->and(ZaakDestructionLog::sole()->data_object_status)->toBe(DataObjectDestructionStatus::Refused);
});
