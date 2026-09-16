<?php

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Enums\ZaakDestructionSource;
use App\Jobs\Archiving\ZaakDestroyNotificationReceived;
use App\Jobs\ProcessOpenNotification;
use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Thread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Archiving\EventloketDataDestroyer;
use App\ValueObjects\OpenNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Http::fake();

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
});

function destroyNotification(string $zaakUrl): OpenNotification
{
    return new OpenNotification(
        actie: 'destroy',
        kanaal: 'zaken',
        resource: 'zaak',
        hoofdObject: $zaakUrl,
        resourceUrl: $zaakUrl,
        aanmaakdatum: now()->toIso8601String(),
    );
}

function localZaakData(Zaak $zaak): array
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

test('a destroy notification removes everything eventloket held about the zaak', function () {
    [$thread, $message, $organiser] = localZaakData($this->zaak);

    expect(Activity::where('subject_type', Zaak::class)->where('subject_id', $this->zaak->id)->exists())->toBeTrue();

    new ZaakDestroyNotificationReceived(destroyNotification($this->zaakUrl))
        ->handle(app(EventloketDataDestroyer::class));

    expect(Zaak::withTrashed()->find($this->zaak->id))->toBeNull()
        ->and(Thread::find($thread->id))->toBeNull()
        ->and(Message::find($message->id))->toBeNull()
        ->and(DB::table('unread_messages')->where('message_id', $message->id)->exists())->toBeFalse()
        ->and(DB::table('thread_user')->where('thread_id', $thread->id)->exists())->toBeFalse()
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and(Activity::where('subject_type', Zaak::class)->where('subject_id', $this->zaak->id)->exists())->toBeFalse();

    // The form submission object in our own Objects API goes with it.
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === $this->dataObjectUrl);

    // The organiser account itself is never touched: they may have zaken at
    // other municipalities.
    expect(User::find($organiser->id))->not->toBeNull();
});

test('the destruction is recorded once, with the connection it happened on', function () {
    new ZaakDestroyNotificationReceived(destroyNotification($this->zaakUrl))
        ->handle(app(EventloketDataDestroyer::class));

    $log = ZaakDestructionLog::sole();

    expect($log->zgw_zaak_url)->toBe($this->zaakUrl)
        ->and($log->zgw_connection)->toBe('main')
        ->and($log->municipality_id)->toBe($this->municipality->id)
        ->and($log->zaaktype_naam)->toBe($this->zaaktype->name)
        ->and($log->source)->toBe(ZaakDestructionSource::Notification)
        ->and($log->reported_at)->toBeNull();
});

test('a replayed notification does not log the destruction twice', function () {
    $notification = destroyNotification($this->zaakUrl);

    new ZaakDestroyNotificationReceived($notification)->handle(app(EventloketDataDestroyer::class));
    new ZaakDestroyNotificationReceived($notification)->handle(app(EventloketDataDestroyer::class));

    expect(ZaakDestructionLog::count())->toBe(1);
});

test('a notification for a zaak we do not know is ignored', function () {
    new ZaakDestroyNotificationReceived(destroyNotification(ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/unknown'))
        ->handle(app(EventloketDataDestroyer::class));

    expect(ZaakDestructionLog::count())->toBe(0)
        ->and(Zaak::withTrashed()->find($this->zaak->id))->not->toBeNull();
});

test('a zaak on a municipality own zgw instance is cleaned up the same way', function () {
    // Eventloket never deletes in their instance, but it does have to clear its
    // own copy once they tell us the zaak is gone.
    $municipality = Municipality::factory()->create();

    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
    ]);

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'connection' => 'gemeente_'.$municipality->id,
        'zgw_zaaktype_url' => 'https://gemeente.example.com/catalogi/api/v1/zaaktypen/1',
    ]);

    $zaakUrl = 'https://gemeente.example.com/zaken/api/v1/zaken/1';

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
        'data_object_url' => null,
    ]);

    new ZaakDestroyNotificationReceived(destroyNotification($zaakUrl))
        ->handle(app(EventloketDataDestroyer::class));

    expect(Zaak::withTrashed()->find($zaak->id))->toBeNull()
        ->and(ZaakDestructionLog::sole()->zgw_connection)->toBe('gemeente_'.$municipality->id);

    // Nothing was deleted on their instance.
    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

test('the webhook routes a destroy notification to the cleanup job', function () {
    Queue::fake();

    new ProcessOpenNotification(destroyNotification($this->zaakUrl))
        ->handle(app(GetIncommingNotificationType::class));

    Queue::assertPushed(ZaakDestroyNotificationReceived::class);
});
