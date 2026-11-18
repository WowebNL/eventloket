<?php

use App\Jobs\Archiving\ZaakDestroyNotificationReceived;
use App\ValueObjects\OpenNotification;

test('an inconclusive read is retried with a growing delay', function () {
    $job = new ZaakDestroyNotificationReceived(new OpenNotification(
        actie: 'destroy',
        kanaal: 'zaken',
        resource: 'zaak',
        hoofdObject: 'https://zgw.example.com/zaken/api/v1/zaken/1',
        resourceUrl: 'https://zgw.example.com/zaken/api/v1/zaken/1',
        aanmaakdatum: now()->toIso8601String(),
        senderConnection: 'main',
    ));

    expect($job->tries)->toBe(4)
        ->and($job->backoff())->toBe([60, 600, 3600]);
});
