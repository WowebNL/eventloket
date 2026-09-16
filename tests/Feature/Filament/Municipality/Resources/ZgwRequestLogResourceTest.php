<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\ZgwRequestLogs\ZgwRequestLogResource;
use App\Models\Municipality;
use App\Models\User;
use App\Models\ZgwRequestLog;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create();
    $this->beheerder = User::factory()->create(['role' => Role::KoppelingBeheerder]);
    $this->municipality->users()->attach($this->beheerder);

    $this->actingAs($this->beheerder);
    Filament::setTenant($this->municipality);
    Filament::bootCurrentPanel();
});

it('scopes the query to the current municipality logs', function () {
    $mine = ZgwRequestLog::create([
        'connection' => "gemeente_{$this->municipality->id}",
        'municipality_id' => $this->municipality->id,
        'method' => 'GET',
        'resource' => '/zaken/api/v1/zaken',
        'status_code' => 200,
        'created_at' => now(),
    ]);

    // A call on the shared "main" connection is not attributable to a
    // municipality and must not surface in any tenant's log viewer. (Filament
    // auto-fills the active tenant on create() while a panel is booted, so the
    // null is forced directly to reflect a real main-connection log.)
    $unattributed = ZgwRequestLog::create([
        'connection' => 'main',
        'method' => 'GET',
        'resource' => '/zaken/api/v1/zaken',
        'status_code' => 200,
        'created_at' => now(),
    ]);
    DB::table('zgw_request_logs')->where('id', $unattributed->id)->update(['municipality_id' => null]);

    $visibleIds = ZgwRequestLogResource::getEloquentQuery()->pluck('id');

    expect($visibleIds)->toContain($mine->id)
        ->not->toContain($unattributed->id);
});

it('is read-only', function () {
    expect(ZgwRequestLogResource::canCreate())->toBeFalse();
});

it('is not accessible to a reviewer', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);
    $this->actingAs($reviewer);

    expect(ZgwRequestLogResource::canAccess())->toBeFalse();
});
