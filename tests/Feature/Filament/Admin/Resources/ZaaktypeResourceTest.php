<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\Zaaktypes\Pages\EditZaaktype;
use App\Filament\Admin\Resources\Zaaktypes\Pages\ListZaaktypes;
use App\Filament\Admin\Resources\Zaaktypes\Schemas\ZaaktypeForm;
use App\Filament\Admin\Resources\Zaaktypes\Tables\ZaaktypesTable;
use App\Filament\Admin\Resources\Zaaktypes\ZaaktypeResource;
use App\Models\User;
use App\Models\Zaaktype;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

covers(
    ZaaktypeResource::class,
    ListZaaktypes::class,
    EditZaaktype::class,
    ZaaktypeForm::class,
    ZaaktypesTable::class,
);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($this->admin);
});

test('admin can view zaaktype list', function () {
    $zaaktypen = Zaaktype::factory()->count(3)->create();

    livewire(ListZaaktypes::class)
        ->assertOk()
        ->assertCanSeeTableRecords($zaaktypen);
});

test('zaaktype table has name column that is searchable', function () {
    Zaaktype::factory()->create(['name' => 'Alpha Zaaktype']);
    Zaaktype::factory()->create(['name' => 'Beta Zaaktype']);

    $alpha = Zaaktype::where('name', 'Alpha Zaaktype')->first();
    $beta = Zaaktype::where('name', 'Beta Zaaktype')->first();

    livewire(ListZaaktypes::class)
        ->searchTable('Alpha')
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);
});

test('zaaktype table has is_active icon column', function () {
    livewire(ListZaaktypes::class)
        ->assertTableColumnExists('is_active');
});

test('zaaktype table has municipality name column', function () {
    livewire(ListZaaktypes::class)
        ->assertTableColumnExists('municipality.name');
});

test('zaaktype table has created_at and updated_at columns', function () {
    livewire(ListZaaktypes::class)
        ->assertTableColumnExists('created_at')
        ->assertTableColumnExists('updated_at');
});

test('zaaktype table has edit action', function () {
    $zaaktype = Zaaktype::factory()->create();

    livewire(ListZaaktypes::class)
        ->assertTableActionExists('edit', record: $zaaktype);
});

test('zaaktype resource has correct model', function () {
    expect(ZaaktypeResource::getModel())->toBe(Zaaktype::class);
});

test('zaaktype can be bulk deleted', function () {
    $zaaktypen = Zaaktype::factory()->count(2)->create();

    livewire(ListZaaktypes::class)
        ->callTableBulkAction('delete', $zaaktypen);

    foreach ($zaaktypen as $zaaktype) {
        expect(Zaaktype::find($zaaktype->id))->toBeNull();
    }
});
