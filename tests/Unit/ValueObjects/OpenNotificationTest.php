<?php

declare(strict_types=1);

use App\ValueObjects\OpenNotification;

/**
 * A queue payload as it was serialized before the notification carried
 * kenmerken. Jobs queued (or failed) then must stay replayable.
 */
const LEGACY_PAYLOAD = 'O:33:"App\ValueObjects\OpenNotification":6:{s:5:"actie";s:6:"create";s:6:"kanaal";s:10:"documenten";s:8:"resource";s:27:"enkelvoudiginformatieobject";s:11:"hoofdObject";s:39:"https://example.com/documenten/api/v1/1";s:11:"resourceUrl";s:39:"https://example.com/documenten/api/v1/1";s:12:"aanmaakdatum";s:25:"2026-01-01T00:00:00+01:00";}';

it('unserializes a payload from before kenmerken existed', function () {
    /** @var OpenNotification $notification */
    $notification = unserialize(LEGACY_PAYLOAD);

    expect($notification)->toBeInstanceOf(OpenNotification::class)
        ->and($notification->kanaal)->toBe('documenten')
        ->and($notification->hoofdObject)->toBe('https://example.com/documenten/api/v1/1')
        // No kenmerken to go on: such a notification resolves through the other
        // layers instead of failing on an uninitialized property.
        ->and($notification->kenmerken)->toBe([])
        ->and($notification->kenmerk('bronorganisatie'))->toBeNull();
});

it('survives a serialize round trip with kenmerken', function () {
    $notification = new OpenNotification(
        actie: 'create',
        kanaal: 'documenten',
        resource: 'enkelvoudiginformatieobject',
        hoofdObject: 'https://example.com/documenten/api/v1/1',
        resourceUrl: 'https://example.com/documenten/api/v1/1',
        aanmaakdatum: '2026-01-01T00:00:00+01:00',
        kenmerken: ['bronorganisatie' => '111111110'],
    );

    /** @var OpenNotification $restored */
    $restored = unserialize(serialize($notification));

    expect($restored->kenmerk('bronorganisatie'))->toBe('111111110')
        ->and($restored->toArray())->toBe($notification->toArray());
});

it('keeps only the kenmerken it can match on', function () {
    expect(OpenNotification::normaliseKenmerken([
        'bronorganisatie' => '111111110',
        'volgnummer' => 3,
        'nested' => ['a' => 'b'],
        'empty' => null,
    ]))->toBe([
        'bronorganisatie' => '111111110',
        'volgnummer' => '3',
    ])
        ->and(OpenNotification::normaliseKenmerken(null))->toBe([])
        ->and(OpenNotification::normaliseKenmerken('not an array'))->toBe([]);
});

it('trims a kenmerk and treats an empty one as absent', function () {
    $notification = new OpenNotification(
        actie: 'create',
        kanaal: 'documenten',
        resource: 'enkelvoudiginformatieobject',
        hoofdObject: 'https://example.com/documenten/api/v1/1',
        resourceUrl: 'https://example.com/documenten/api/v1/1',
        aanmaakdatum: '2026-01-01T00:00:00+01:00',
        kenmerken: ['bronorganisatie' => '  111111110 ', 'verantwoordelijkeOrganisatie' => '   '],
    );

    expect($notification->kenmerk('bronorganisatie'))->toBe('111111110')
        ->and($notification->kenmerk('verantwoordelijkeOrganisatie'))->toBeNull()
        ->and($notification->kenmerk('afwezig'))->toBeNull();
});
