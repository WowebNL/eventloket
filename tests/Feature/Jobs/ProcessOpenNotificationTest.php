<?php

declare(strict_types=1);

/**
 * Eigenschappen are written to the zaaksysteem one at a time, so a single zaak
 * fires a whole series of zaakeigenschap notifications in quick succession, all
 * carrying the same hoofdObject. Each one used to become its own cache-clear
 * job with a full ZGW refetch. These tests pin that the series collapses into
 * one refresh per zaak, while separate zaken keep their own.
 */

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Jobs\ProcessOpenNotification;
use App\Jobs\Zaak\ClearZaakCache;
use App\Jobs\ZaakStatusNotificationReceived;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // The unique lock lives in the cache, so a leftover lock would hide the
    // very behaviour under test.
    Cache::flush();
    Queue::fake();
});

const PON_ZAAK_URL = 'https://zgw.example.com/zaken/api/v1/zaken/1';

function zaakeigenschapNotification(string $zaakUrl, string $actie = 'update'): OpenNotification
{
    return new OpenNotification(
        actie: $actie,
        kanaal: 'zaken',
        resource: 'zaakeigenschap',
        hoofdObject: $zaakUrl,
        resourceUrl: $zaakUrl.'/zaakeigenschappen/'.uniqid(),
        aanmaakdatum: now()->toIso8601String(),
    );
}

function processNotification(OpenNotification $notification): void
{
    (new ProcessOpenNotification($notification))->handle(new GetIncommingNotificationType);
}

test('a series of zaakeigenschap notifications for one zaak collapses into a single refresh', function () {
    // What a regular submission produces: the eigenschappen are created one by
    // one, so a dozen notifications describe a single state to read back.
    processNotification(zaakeigenschapNotification(PON_ZAAK_URL, 'create'));
    foreach (range(1, 11) as $ignored) {
        processNotification(zaakeigenschapNotification(PON_ZAAK_URL, 'create'));
    }
    processNotification(zaakeigenschapNotification(PON_ZAAK_URL));

    Queue::assertPushed(ClearZaakCache::class, 1);
});

test('the refresh is delayed so the notifications of one zaak can catch up with each other', function () {
    processNotification(zaakeigenschapNotification(PON_ZAAK_URL));

    Queue::assertPushed(ClearZaakCache::class, fn (ClearZaakCache $job): bool => $job->delay !== null);
});

test('the refresh is unique per zaak, so every zaak is still refreshed', function () {
    $other = 'https://zgw.example.com/zaken/api/v1/zaken/2';

    processNotification(zaakeigenschapNotification(PON_ZAAK_URL));
    processNotification(zaakeigenschapNotification($other));
    processNotification(zaakeigenschapNotification(PON_ZAAK_URL));

    Queue::assertPushed(ClearZaakCache::class, 2);
    Queue::assertPushed(ClearZaakCache::class, fn (ClearZaakCache $job): bool => $job->uniqueId() === 'clear-zaak-cache:'.md5(PON_ZAAK_URL));
    Queue::assertPushed(ClearZaakCache::class, fn (ClearZaakCache $job): bool => $job->uniqueId() === 'clear-zaak-cache:'.md5($other));
});

test('a single zaakeigenschap notification still refreshes its zaak', function () {
    // The unchanged case: one notification, one refresh, for that zaak.
    processNotification(zaakeigenschapNotification(PON_ZAAK_URL));

    Queue::assertPushed(ClearZaakCache::class, 1);
    Queue::assertPushed(ClearZaakCache::class, fn (ClearZaakCache $job): bool => $job->uniqueId() === 'clear-zaak-cache:'.md5(PON_ZAAK_URL));
});

test('a status notification is dispatched as before', function () {
    // Regression anchor: only the zaakeigenschap branch changed.
    processNotification(new OpenNotification(
        actie: 'create',
        kanaal: 'zaken',
        resource: 'status',
        hoofdObject: PON_ZAAK_URL,
        resourceUrl: PON_ZAAK_URL.'/statussen/1',
        aanmaakdatum: now()->toIso8601String(),
    ));

    Queue::assertPushed(ZaakStatusNotificationReceived::class, 1);
    Queue::assertNotPushed(ClearZaakCache::class);
});
