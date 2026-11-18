<?php

use App\Jobs\Archiving\ZaakDestroyNotificationReceived;
use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Archiving\EventloketDataDestroyer;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

/**
 * The local data and the row that records its destruction go together: a
 * failure to write the record leaves the zaak in place and fails visibly, so
 * a retry can finish the job and nothing disappears without evidence.
 */
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['detail' => 'not found'], 404));

    Config::set('openzaak.objectsapi.url', 'https://objects.example.com/');

    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/z1',
        'data_object_url' => null,
    ]);
});

function failLogInsert(): void
{
    Event::listen('eloquent.creating: '.ZaakDestructionLog::class, function (): never {
        throw new RuntimeException('The destruction log could not be written.');
    });
}

function restoreLogInsert(): void
{
    Event::forget('eloquent.creating: '.ZaakDestructionLog::class);
}

test('a failing log insert keeps the zaak and a second run records it once', function () {
    failLogInsert();

    expect(fn () => app(EventloketDataDestroyer::class)->destroy($this->zaak))
        ->toThrow(RuntimeException::class);

    expect(Zaak::withTrashed()->find($this->zaak->id))->not->toBeNull()
        ->and(ZaakDestructionLog::count())->toBe(0);

    restoreLogInsert();

    app(EventloketDataDestroyer::class)->destroy(Zaak::withTrashed()->findOrFail($this->zaak->id));

    expect(Zaak::withTrashed()->find($this->zaak->id))->toBeNull()
        ->and(ZaakDestructionLog::count())->toBe(1);
});

test('the notification job fails visibly when the log cannot be written', function () {
    failLogInsert();

    $job = new ZaakDestroyNotificationReceived(new OpenNotification(
        actie: 'destroy',
        kanaal: 'zaken',
        resource: 'zaak',
        hoofdObject: (string) $this->zaak->zgw_zaak_url,
        resourceUrl: (string) $this->zaak->zgw_zaak_url,
        aanmaakdatum: now()->toIso8601String(),
        senderConnection: 'main',
    ));

    expect(fn () => $job->handle(app(EventloketDataDestroyer::class)))
        ->toThrow(RuntimeException::class);

    expect(Zaak::withTrashed()->find($this->zaak->id))->not->toBeNull();
});
