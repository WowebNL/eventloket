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
        ->and(classify('update', 'documenten', 'enkelvoudiginformatieobject'))->toBe(OpenNotificationType::UpdatedZaakDocument)
        ->and(classify('create', 'besluiten', 'besluit'))->toBeNull();
});
