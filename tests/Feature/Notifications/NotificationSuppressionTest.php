<?php

declare(strict_types=1);

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\NewZaak;

function zaakWithSuppression(bool $suppress): Zaak
{
    $municipality = Municipality::factory()->create();

    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
        'suppress_notifications' => $suppress,
    ]);

    // An activated connection and an own-instance zaaktype: only then does the
    // zaak actually run on this connection, and only then do its flags apply.
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'connection' => "gemeente_{$municipality->id}",
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => null,
    ]);
}

test('a suppressed connection delivers no channels', function () {
    $zaak = zaakWithSuppression(true);
    $user = User::factory()->create();

    expect((new NewZaak($zaak))->via($user))->toBe([]);
});

test('a normal connection delivers the default channels', function () {
    $zaak = zaakWithSuppression(false);
    $user = User::factory()->create();

    expect((new NewZaak($zaak))->via($user))->toContain('mail');
});
