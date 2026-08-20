<?php

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Enums\OpenNotificationType;
use App\ValueObjects\OpenNotification;

function classify(string $actie, string $kanaal, string $resource): ?OpenNotificationType
{
    return (new GetIncommingNotificationType)->handle(new OpenNotification(
        actie: $actie,
        kanaal: $kanaal,
        resource: $resource,
        hoofdObject: 'https://example.com/resource/1',
        resourceUrl: 'https://example.com/resource/1',
        aanmaakdatum: now(),
    ));
}

test('every actie and resource on the zaaktypen channel classifies as ZaaktypeChanged', function (string $actie, string $resource) {
    expect(classify($actie, 'zaaktypen', $resource))->toBe(OpenNotificationType::ZaaktypeChanged);
})->with([
    ['create', 'zaaktype'],
    ['partial_update', 'zaaktype'],
    ['destroy', 'zaaktype'],
    ['create', 'statustype'],
    ['destroy', 'resultaattype'],
]);

test('existing channels keep their classification', function () {
    expect(classify('partial_update', 'zaken', 'zaakeigenschap'))->toBe(OpenNotificationType::UpdateZaakEigenschap)
        ->and(classify('create', 'zaken', 'status'))->toBe(OpenNotificationType::ZaakStatusChanged)
        ->and(classify('create', 'documenten', 'enkelvoudiginformatieobject'))->toBe(OpenNotificationType::NewZaakDocument)
        ->and(classify('update', 'documenten', 'enkelvoudiginformatieobject'))->toBe(OpenNotificationType::UpdatedZaakDocument);
});

test('the besluiten channel is classified so a zaak drops its cached besluiten', function () {
    // We subscribe to this channel; before it was handled the notification was
    // dropped and a besluit taken in the ZGW backend never reached the zaak.
    expect(classify('create', 'besluiten', 'besluit'))->toBe(OpenNotificationType::BesluitChanged)
        ->and(classify('update', 'besluiten', 'besluit'))->toBe(OpenNotificationType::BesluitChanged)
        ->and(classify('create', 'besluiten', 'besluitinformatieobject'))->toBe(OpenNotificationType::BesluitChanged);
});
