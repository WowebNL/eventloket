<?php

use App\Enums\DestructionItemStatus;
use App\Enums\ZaakDestructionSource;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $this->zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/z1';

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $this->zaakUrl,
        'data_object_url' => null,
    ]);

    $this->list = DestructionList::factory()->create(['municipality_id' => $this->municipality->id]);
});

function deletedItem(string $zaakUrl, Zaak $zaak, DestructionList $list, $destroyedAt): DestructionListItem
{
    return DestructionListItem::factory()->create([
        'destruction_list_id' => $list->id,
        'zaak_id' => $zaak->id,
        'zgw_zaak_url' => $zaakUrl,
        'zaaknummer' => $zaak->public_id,
        'status' => DestructionItemStatus::Deleted,
        'destroyed_at' => $destroyedAt,
    ]);
}

test('a zaak whose destroy notification never arrived is cleaned up', function () {
    deletedItem($this->zaakUrl, $this->zaak, $this->list, now()->subDay());

    // Gone in ZGW, still here locally: the notification was lost.
    Http::fake(fn () => Http::response(['detail' => 'not found'], 404));

    $this->artisan('archiving:reconcile-destroyed-zaken')->assertSuccessful();

    expect(Zaak::withTrashed()->find($this->zaak->id))->toBeNull()
        ->and(ZaakDestructionLog::sole()->source)->toBe(ZaakDestructionSource::Reconciliation);
});

test('a zaak that still exists in zgw is left alone', function () {
    deletedItem($this->zaakUrl, $this->zaak, $this->list, now()->subDay());

    Http::fake(fn () => Http::response(['url' => $this->zaakUrl, 'identificatie' => 'ZAAK-1'], 200));

    $this->artisan('archiving:reconcile-destroyed-zaken')->assertSuccessful();

    expect(Zaak::withTrashed()->find($this->zaak->id))->not->toBeNull()
        ->and(ZaakDestructionLog::count())->toBe(0);
});

test('a recent destruction is left to the notification', function () {
    // Inside the grace period: the webhook is probably still on its way.
    deletedItem($this->zaakUrl, $this->zaak, $this->list, now()->subMinutes(5));

    Http::fake(fn () => Http::response(['detail' => 'not found'], 404));

    $this->artisan('archiving:reconcile-destroyed-zaken')
        ->expectsOutput('Nothing to reconcile.')
        ->assertSuccessful();

    expect(Zaak::withTrashed()->find($this->zaak->id))->not->toBeNull();
});

test('a zaak the notification already cleaned up is not touched again', function () {
    $item = deletedItem($this->zaakUrl, $this->zaak, $this->list, now()->subDay());

    // The notification did arrive: the local zaak is gone and the FK nulled.
    $this->zaak->forceDelete();

    Http::fake(fn () => Http::response(['detail' => 'not found'], 404));

    $this->artisan('archiving:reconcile-destroyed-zaken')->assertSuccessful();

    expect(ZaakDestructionLog::count())->toBe(0)
        ->and($item->refresh()->zaak_id)->toBeNull();
});

test('a dry run destroys nothing', function () {
    deletedItem($this->zaakUrl, $this->zaak, $this->list, now()->subDay());

    Http::fake(fn () => Http::response(['detail' => 'not found'], 404));

    $this->artisan('archiving:reconcile-destroyed-zaken', ['--dry-run' => true])->assertSuccessful();

    expect(Zaak::withTrashed()->find($this->zaak->id))->not->toBeNull()
        ->and(ZaakDestructionLog::count())->toBe(0);
});
