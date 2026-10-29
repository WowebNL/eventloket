<?php

declare(strict_types=1);

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function zaakForConnection(array $connectionAttributes = [], bool $withConnection = true, bool $active = true): Zaak
{
    $municipality = Municipality::factory()->create();

    if ($withConnection) {
        $factory = MunicipalityZgwConnection::factory();
        if ($active) {
            $factory = $factory->active();
        }
        $factory->create(array_merge([
            'municipality_id' => $municipality->id,
        ], $connectionAttributes));
    }

    // An own-instance zaaktype: the `connection` column records which instance
    // hosts it, and it defaults to 'main'. A zaak on a main row reads from main
    // and therefore keeps main's behaviour, which is covered separately below.
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'connection' => "gemeente_{$municipality->id}",
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => null,
    ]);
}

test('without a connection row all behaviour defaults to the full feature set', function () {
    $zaak = zaakForConnection(withConnection: false);

    expect($zaak->behandelaarCanChangeStatus())->toBeTrue();
    expect($zaak->behandelaarCanEditRisicoClassificatie())->toBeTrue();
    expect($zaak->showsTab('besluiten'))->toBeTrue();
    expect($zaak->showsTab('bestanden'))->toBeTrue();
    expect($zaak->showsTab('adviesvragen'))->toBeTrue();
    expect($zaak->showsTab('organisatievragen'))->toBeTrue();
    expect($zaak->suppressesNotifications())->toBeFalse();
    expect($zaak->organiserCanWithdraw())->toBeTrue();
});

test('a municipality with its own connection cannot edit the risico classificatie', function () {
    // The edit writes the eigenschappen by hardcoded naam and bypasses the
    // per-municipality blueprint, so it is hidden once a connection exists.
    $zaak = zaakForConnection();

    expect($zaak->behandelaarCanEditRisicoClassificatie())->toBeFalse();
});

test('lock_status_for_behandelaar blocks status changes', function () {
    $zaak = zaakForConnection(['lock_status_for_behandelaar' => true]);

    expect($zaak->behandelaarCanChangeStatus())->toBeFalse();
});

test('tab toggles are reflected by showsTab', function () {
    $zaak = zaakForConnection([
        'show_besluiten_tab' => false,
        'show_bestanden_tab' => false,
        'show_adviesvragen_tab' => true,
        'show_organisatievragen_tab' => false,
    ]);

    expect($zaak->showsTab('besluiten'))->toBeFalse();
    expect($zaak->showsTab('bestanden'))->toBeFalse();
    expect($zaak->showsTab('adviesvragen'))->toBeTrue();
    expect($zaak->showsTab('organisatievragen'))->toBeFalse();
});

test('suppress_notifications is reflected by suppressesNotifications', function () {
    $zaak = zaakForConnection(['suppress_notifications' => true]);

    expect($zaak->suppressesNotifications())->toBeTrue();
});

test('a connection allows organiser withdrawal by default', function () {
    $zaak = zaakForConnection();

    expect($zaak->organiserCanWithdraw())->toBeTrue();
});

test('allow_organiser_withdrawal disabled blocks organiser withdrawal', function () {
    $zaak = zaakForConnection(['allow_organiser_withdrawal' => false]);

    expect($zaak->organiserCanWithdraw())->toBeFalse();
});

test('a OneGround connection always blocks organiser withdrawal', function () {
    // Even with withdrawal explicitly enabled, OneGround cannot complete it.
    $zaak = zaakForConnection([
        'allow_organiser_withdrawal' => true,
        'is_oneground' => true,
    ]);

    expect($zaak->organiserCanWithdraw())->toBeFalse();
});

test('a connection that is not activated yet keeps the default behaviour', function () {
    // Until activation the zaak reads from main, so the flags of the connection
    // being prepared must not describe it. They used to, which meant the tabs and
    // the status lock followed one instance while the data came from another.
    $zaak = zaakForConnection([
        'show_besluiten_tab' => false,
        'show_bestanden_tab' => false,
        'lock_status_for_behandelaar' => true,
        'is_oneground' => true,
    ], active: false);

    expect($zaak->zgwConnectionName())->toBe('main')
        ->and($zaak->showsTab('besluiten'))->toBeTrue()
        ->and($zaak->showsTab('bestanden'))->toBeTrue()
        ->and($zaak->behandelaarCanChangeStatus())->toBeTrue()
        ->and($zaak->organiserCanWithdraw())->toBeTrue();
});

test('a zaak on a main-fallback zaaktype keeps the default behaviour', function () {
    // The zaaktype records which instance hosts it. A main-catalogus row reads
    // from main even while the municipality runs its own instance, so the
    // connection's flags do not apply to this zaak either.
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
        'show_bestanden_tab' => false,
        'lock_status_for_behandelaar' => true,
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => $municipality->id,
            'connection' => 'main',
        ])->id,
        'zgw_zaak_url' => null,
    ]);

    expect($zaak->zgwConnectionName())->toBe('main')
        ->and($zaak->showsTab('bestanden'))->toBeTrue()
        ->and($zaak->behandelaarCanChangeStatus())->toBeTrue();
});

test('a connection does not offer read-only documents for advisories by default', function () {
    $municipality = Municipality::factory()->create();

    // A row written without the column, as every row that existed before it.
    DB::table('municipality_zgw_connections')->insert([
        'municipality_id' => $municipality->id,
        'show_bestanden_tab' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $connection = MunicipalityZgwConnection::query()->where('municipality_id', $municipality->id)->firstOrFail();

    expect($connection->show_bestanden_tab_read_only_for_advisories)->toBeFalse();
});

test('switching the bestanden tab on through the model switches read-only documents for advisories off', function () {
    $connection = MunicipalityZgwConnection::factory()->bestandenReadOnlyForAdvisories()->create();

    $connection->update(['show_bestanden_tab' => true]);

    expect($connection->fresh())
        ->show_bestanden_tab->toBeTrue()
        ->show_bestanden_tab_read_only_for_advisories->toBeFalse();
});

test('a new connection that leaves the tab at its default cannot store read-only documents for advisories', function () {
    $municipality = Municipality::factory()->create();

    // The tab column defaults to shown, so the opt-in is dropped on create.
    $connection = MunicipalityZgwConnection::query()->create([
        'municipality_id' => $municipality->id,
        'show_bestanden_tab_read_only_for_advisories' => true,
    ]);

    expect($connection->fresh())
        ->show_bestanden_tab->toBeTrue()
        ->show_bestanden_tab_read_only_for_advisories->toBeFalse();
});

test('a write outside the model cannot combine the bestanden tab with read-only documents for advisories', function () {
    $connection = MunicipalityZgwConnection::factory()->bestandenReadOnlyForAdvisories()->create();

    expect(fn () => DB::table('municipality_zgw_connections')
        ->where('id', $connection->id)
        ->update(['show_bestanden_tab' => true]))
        ->toThrow(QueryException::class, 'municipality_zgw_connections_read_only_docs_need_tab_off');
});
