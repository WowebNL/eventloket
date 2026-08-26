<?php

use App\Enums\DestructionItemStatus;
use App\Enums\DestructionListStatus;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Jobs\Archiving\ExecuteZaakDestruction;
use App\Jobs\Archiving\StartDestructionListDeletion;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\Thread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Archiving\ZaakDestructionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Storage::fake('local');

    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $this->zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/z1';
    $this->dataObjectUrl = 'https://objects.example.com/api/v2/objects/obj1';

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $this->zaakUrl,
        'data_object_url' => $this->dataObjectUrl,
    ]);

    $this->list = DestructionList::factory()->deleting()->create(['municipality_id' => $this->municipality->id]);
    $this->item = DestructionListItem::factory()->create([
        'destruction_list_id' => $this->list->id,
        'zaak_id' => $this->zaak->id,
        'zgw_zaak_url' => $this->zaakUrl,
        'zaaknummer' => $this->zaak->public_id,
    ]);
});

function zgwZaakPayload(string $url, array $overrides = []): array
{
    return array_merge([
        'uuid' => 'z1',
        'url' => $url,
        'identificatie' => 'ZAAK-123',
        'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        'omschrijving' => 'Test zaak',
        'startdatum' => now()->subYears(2)->format('Y-m-d'),
        'registratiedatum' => now()->subYears(2)->format('Y-m-d'),
        'einddatum' => now()->subYear()->format('Y-m-d'),
        'einddatumGepland' => null,
        'uiterlijkeEinddatumAfdoening' => null,
        'bronorganisatie' => '123456789',
        'zaakgeometrie' => null,
        'archiefnominatie' => 'vernietigen',
        'archiefactiedatum' => now()->subDay()->format('Y-m-d'),
        'archiefstatus' => 'nog_te_archiveren',
    ], $overrides);
}

/**
 * Fakes the complete ZGW surface used during destruction:
 * one besluit with a document, two zaak documents of which one is shared
 * with another zaak.
 */
function fakeZgwDestructionApi(string $zaakUrl, array $zaakOverrides = [], array $failUrls = []): array
{
    $base = ZgwHttpFake::$baseUrl;

    $urls = [
        'besluit' => $base.'/besluiten/api/v1/besluiten/b1',
        'besluitinformatieobject' => $base.'/besluiten/api/v1/besluitinformatieobjecten/bio1',
        'besluit_document' => $base.'/documenten/api/v1/enkelvoudiginformatieobjecten/d3',
        'zaakinformatieobject_1' => $base.'/zaken/api/v1/zaakinformatieobjecten/zio1',
        'zaakinformatieobject_2' => $base.'/zaken/api/v1/zaakinformatieobjecten/zio2',
        'document' => $base.'/documenten/api/v1/enkelvoudiginformatieobjecten/d1',
        'shared_document' => $base.'/documenten/api/v1/enkelvoudiginformatieobjecten/d2',
    ];

    Http::fake(function ($request) use ($zaakUrl, $zaakOverrides, $failUrls, $urls) {
        $url = $request->url();
        $urlWithoutQuery = strtok($url, '?');

        if ($request->method() === 'DELETE') {
            if (in_array($urlWithoutQuery, $failUrls)) {
                return Http::response(['detail' => 'server error'], 500);
            }

            return Http::response(null, 204);
        }

        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);

        return match (true) {
            $urlWithoutQuery === $zaakUrl => Http::response(zgwZaakPayload($zaakUrl, $zaakOverrides), 200),
            str_contains($url, '/besluiten/api/v1/besluiten') => Http::response([
                ['url' => $urls['besluit'], 'besluittype' => 'bt', 'zaak' => $zaakUrl],
            ], 200),
            str_contains($url, '/besluiten/api/v1/besluitinformatieobjecten') => Http::response([
                ['url' => $urls['besluitinformatieobject'], 'informatieobject' => $urls['besluit_document'], 'besluit' => $urls['besluit']],
            ], 200),
            str_contains($url, '/zaken/api/v1/zaakinformatieobjecten') => Http::response([
                ['url' => $urls['zaakinformatieobject_1'], 'informatieobject' => $urls['document'], 'zaak' => $zaakUrl],
                ['url' => $urls['zaakinformatieobject_2'], 'informatieobject' => $urls['shared_document'], 'zaak' => $zaakUrl],
            ], 200),
            str_contains($url, '/documenten/api/v1/objectinformatieobjecten') => Http::response(
                ($query['informatieobject'] ?? null) === $urls['shared_document']
                    ? [['url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/objectinformatieobjecten/oio-other', 'object' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/other']]
                    : [],
                200,
            ),
            default => Http::response([], 200),
        };
    });

    return $urls;
}

function createLocalZaakData(Zaak $zaak): array
{
    $organiser = User::factory()->create(['role' => Role::Organiser]);

    $thread = Thread::factory()->create([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Organiser,
        'title' => 'Test thread',
        'created_by' => $organiser->id,
    ]);

    $message = Model::withoutEvents(fn () => Message::factory()->create([
        'thread_id' => $thread->id,
        'user_id' => $organiser->id,
    ]));

    $organiser->unreadMessages()->attach($message->id);
    $thread->assignedUsers()->attach($organiser->id);

    DB::table('notifications')->insert([
        'id' => Str::uuid(),
        'type' => 'App\\Notifications\\NewZaak',
        'notifiable_type' => User::class,
        'notifiable_id' => $organiser->id,
        'data' => json_encode(['body' => 'test', 'viewUrl' => "https://eventloket.test/zaken/{$zaak->id}"]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$thread, $message, $organiser];
}

test('destroys the zaak in openzaak and locally, keeping shared documents', function () {
    $urls = fakeZgwDestructionApi($this->zaakUrl);

    [$thread, $message, $organiser] = createLocalZaakData($this->zaak);

    expect(Activity::where('subject_type', Zaak::class)->where('subject_id', $this->zaak->id)->exists())->toBeTrue();

    new ExecuteZaakDestruction($this->item)->handle(app(ZaakDestructionService::class));

    // OpenZaak: besluit, its document, the unshared document and the zaak are deleted
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === $urls['besluit']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === $urls['besluit_document']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === $urls['document']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === $this->zaakUrl);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === $this->dataObjectUrl);

    // The shared document is kept
    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === $urls['shared_document']);

    // All local data is gone
    expect(Zaak::withTrashed()->find($this->zaak->id))->toBeNull()
        ->and(Thread::find($thread->id))->toBeNull()
        ->and(Message::find($message->id))->toBeNull()
        ->and(DB::table('unread_messages')->where('message_id', $message->id)->exists())->toBeFalse()
        ->and(DB::table('thread_user')->where('thread_id', $thread->id)->exists())->toBeFalse()
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and(Activity::where('subject_type', Zaak::class)->where('subject_id', $this->zaak->id)->exists())->toBeFalse();

    // The organiser account itself is untouched
    expect(User::find($organiser->id))->not->toBeNull();

    $this->item->refresh();

    expect($this->item->status)->toBe(DestructionItemStatus::Deleted)
        ->and($this->item->zaak_id)->toBeNull()
        ->and($this->item->destroyed_at)->not->toBeNull();
});

test('skips a zaak that is no longer eligible according to openzaak', function () {
    fakeZgwDestructionApi($this->zaakUrl, ['archiefnominatie' => 'blijvend_bewaren']);

    new ExecuteZaakDestruction($this->item)->handle(app(ZaakDestructionService::class));

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');

    expect($this->item->refresh()->status)->toBe(DestructionItemStatus::Skipped)
        ->and(Zaak::withTrashed()->find($this->zaak->id))->not->toBeNull();
});

test('cleans up local data when the zaak is already gone in openzaak', function () {
    Http::fake(function ($request) {
        if ($request->method() === 'DELETE') {
            return Http::response(null, 204);
        }

        return Http::response(['detail' => 'not found'], 404);
    });

    new ExecuteZaakDestruction($this->item)->handle(app(ZaakDestructionService::class));

    expect($this->item->refresh()->status)->toBe(DestructionItemStatus::Deleted)
        ->and(Zaak::withTrashed()->find($this->zaak->id))->toBeNull();
});

test('a failing item does not stop other items and can be retried', function () {
    // The second zaak's DELETE in OpenZaak fails
    $failingZaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/z2';

    $failingZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $failingZaakUrl,
        'data_object_url' => null,
    ]);

    $failingItem = DestructionListItem::factory()->create([
        'destruction_list_id' => $this->list->id,
        'zaak_id' => $failingZaak->id,
        'zgw_zaak_url' => $failingZaakUrl,
    ]);

    $zaakDeleteFails = true;

    Http::fake(function ($request) use ($failingZaakUrl, &$zaakDeleteFails) {
        $urlWithoutQuery = strtok($request->url(), '?');

        if ($request->method() === 'DELETE') {
            return $zaakDeleteFails && $urlWithoutQuery === $failingZaakUrl
                ? Http::response(['detail' => 'server error'], 500)
                : Http::response(null, 204);
        }

        if (in_array($urlWithoutQuery, [$this->zaakUrl, $failingZaakUrl])) {
            return Http::response(zgwZaakPayload($urlWithoutQuery), 200);
        }

        return Http::response([], 200);
    });

    StartDestructionListDeletion::dispatchSync($this->list);

    expect($this->item->refresh()->status)->toBe(DestructionItemStatus::Deleted)
        ->and($failingItem->refresh()->status)->toBe(DestructionItemStatus::Failed)
        ->and($failingItem->failure_reason)->not->toBeNull()
        ->and($this->list->refresh()->status)->toBe(DestructionListStatus::Failed)
        ->and($this->list->destruction_report_id)->toBeNull();

    // Retry: OpenZaak now accepts the delete; only the failed item is processed again
    $zaakDeleteFails = false;

    $this->list->transitionTo(DestructionListStatus::Deleting);

    StartDestructionListDeletion::dispatchSync($this->list);

    expect($failingItem->refresh()->status)->toBe(DestructionItemStatus::Deleted)
        ->and($this->list->refresh()->status)->toBe(DestructionListStatus::Deleted)
        ->and($this->list->destruction_report_id)->not->toBeNull();
});
