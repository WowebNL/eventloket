<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\ZgwRequestLogs\Pages\ListZgwRequestLogs;
use App\Filament\Admin\Resources\ZgwRequestLogs\ZgwRequestLogResource;
use App\Models\Municipality;
use App\Models\User;
use App\Models\ZgwRequestLog;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

covers(
    ZgwRequestLogResource::class,
    ListZgwRequestLogs::class,
);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    actingAs($this->admin);
});

function logFor(?Municipality $municipality, string $resource, ?User $user = null): ZgwRequestLog
{
    $log = ZgwRequestLog::create([
        'connection' => $municipality ? "gemeente_{$municipality->id}" : 'main',
        'municipality_id' => $municipality?->id,
        'user_id' => $user?->id,
        'method' => 'GET',
        'resource' => $resource,
        'status_code' => 200,
        'created_at' => now(),
    ]);

    // municipality_id is not fillable; force the value (including the main null).
    DB::table('zgw_request_logs')->where('id', $log->id)->update(['municipality_id' => $municipality?->id]);

    return $log->refresh();
}

it('shows logs from every municipality and the shared main connection', function () {
    $munA = Municipality::factory()->create();
    $munB = Municipality::factory()->create();

    $a = logFor($munA, '/zaken/api/v1/zaken');
    $b = logFor($munB, '/catalogi/api/v1/zaaktypen');
    $main = logFor(null, '/zaken/api/v1/statussen');

    livewire(ListZgwRequestLogs::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$a, $b, $main]);
});

it('filters by connection', function () {
    $munA = Municipality::factory()->create();
    $a = logFor($munA, '/zaken/api/v1/zaken');
    $main = logFor(null, '/zaken/api/v1/statussen');

    livewire(ListZgwRequestLogs::class)
        ->filterTable('connection', 'main')
        ->assertCanSeeTableRecords([$main])
        ->assertCanNotSeeTableRecords([$a]);
});

it('searches on the resource path', function () {
    $munA = Municipality::factory()->create();
    $zaken = logFor($munA, '/zaken/api/v1/zaken');
    $catalogi = logFor($munA, '/catalogi/api/v1/zaaktypen');

    livewire(ListZgwRequestLogs::class)
        ->searchTable('catalogi')
        ->assertCanSeeTableRecords([$catalogi])
        ->assertCanNotSeeTableRecords([$zaken]);
});

it('searches on the logged user name', function () {
    $munA = Municipality::factory()->create();
    $alice = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Logger']);

    $withUser = logFor($munA, '/zaken/api/v1/zaken', $alice);
    $withoutUser = logFor($munA, '/catalogi/api/v1/zaaktypen');

    livewire(ListZgwRequestLogs::class)
        ->searchTable('Alice Logger')
        ->assertCanSeeTableRecords([$withUser])
        ->assertCanNotSeeTableRecords([$withoutUser]);
});
