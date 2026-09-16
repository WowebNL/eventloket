<?php

declare(strict_types=1);

/**
 * A zaaknummer is only unique within the ZGW instance that issued it, so two
 * municipalities can carry the same number. This command takes a number and
 * dispatches a job that writes the zaak's documents into the ZGW instance of
 * every municipality its route passes through, so resolving the number to the
 * wrong zaak moves one municipality's data into another's instance. It
 * therefore has to either be told which zaak is meant, or refuse.
 */

use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Zaak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

const DUPLICATE_NUMBER = 'ZAAK-2026-000000042';

/**
 * The same number on two connections, the situation the scoped unique index on
 * `zaken` allows.
 *
 * @return array{0: Zaak, 1: Zaak}
 */
function zakenSharingANumber(): array
{
    $onMain = Zaak::factory()->create([
        'public_id' => DUPLICATE_NUMBER,
        'zgw_connection' => 'main',
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/on-main',
    ]);

    $onOwn = Zaak::factory()->create([
        'public_id' => DUPLICATE_NUMBER,
        'zgw_connection' => 'gemeente_7',
        'zgw_zaak_url' => 'https://gemeente.example.com/zaken/api/v1/zaken/on-own',
    ]);

    return [$onMain, $onOwn];
}

test('refuses an ambiguous number and names every candidate with its connection', function () {
    [$onMain, $onOwn] = zakenSharingANumber();

    $this->artisan('zaak:create-doorkomst-zaken', ['public_id' => DUPLICATE_NUMBER])
        ->expectsOutputToContain('More than one zaak carries public_id '.DUPLICATE_NUMBER)
        ->expectsOutputToContain("main (zaak {$onMain->id})")
        ->expectsOutputToContain("gemeente_7 (zaak {$onOwn->id})")
        ->assertFailed();

    Queue::assertNotPushed(CreateDoorkomstZaken::class);
});

test('processes the zaak on the connection it is given', function () {
    [, $onOwn] = zakenSharingANumber();

    $this->artisan('zaak:create-doorkomst-zaken', [
        'public_id' => DUPLICATE_NUMBER,
        '--connection' => 'gemeente_7',
    ])->assertSuccessful();

    Queue::assertPushed(
        CreateDoorkomstZaken::class,
        fn (CreateDoorkomstZaken $job): bool => $job->zaak->is($onOwn),
    );
});

test('keeps working without the option when only one zaak carries the number', function () {
    $zaak = Zaak::factory()->create([
        'public_id' => 'ZAAK-2026-000000043',
        'zgw_connection' => 'main',
        'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/only-one',
    ]);

    $this->artisan('zaak:create-doorkomst-zaken', ['public_id' => 'ZAAK-2026-000000043'])
        ->assertSuccessful();

    Queue::assertPushed(
        CreateDoorkomstZaken::class,
        fn (CreateDoorkomstZaken $job): bool => $job->zaak->is($zaak),
    );
});

test('fails on a connection that does not carry the number, and says where it does', function () {
    zakenSharingANumber();

    $this->artisan('zaak:create-doorkomst-zaken', [
        'public_id' => DUPLICATE_NUMBER,
        '--connection' => 'gemeente_99',
    ])
        ->expectsOutputToContain('on connection gemeente_99')
        ->expectsOutputToContain('That number does exist on: gemeente_7, main.')
        ->assertFailed();

    Queue::assertNotPushed(CreateDoorkomstZaken::class);
});

test('fails on an unknown number', function () {
    $this->artisan('zaak:create-doorkomst-zaken', ['public_id' => 'ZAAK-2026-000000099'])
        ->expectsOutputToContain('No zaak found with public_id: ZAAK-2026-000000099')
        ->assertFailed();

    Queue::assertNotPushed(CreateDoorkomstZaken::class);
});

test('fails when the resolved zaak has no zgw_zaak_url', function () {
    Zaak::factory()->create([
        'public_id' => 'ZAAK-2026-000000044',
        'zgw_connection' => 'main',
        'zgw_zaak_url' => null,
    ]);

    $this->artisan('zaak:create-doorkomst-zaken', ['public_id' => 'ZAAK-2026-000000044'])
        ->expectsOutputToContain('has no zgw_zaak_url')
        ->assertFailed();

    Queue::assertNotPushed(CreateDoorkomstZaken::class);
});
