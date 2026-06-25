<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\StatusResultaatColors\Pages\CreateStatusResultaatColor;
use App\Filament\Admin\Resources\StatusResultaatColors\Pages\EditStatusResultaatColor;
use App\Filament\Admin\Resources\StatusResultaatColors\Pages\ListStatusResultaatColors;
use App\Filament\Admin\Resources\StatusResultaatColors\Schemas\StatusResultaatColorForm;
use App\Filament\Admin\Resources\StatusResultaatColors\StatusResultaatColorResource;
use App\Filament\Admin\Resources\StatusResultaatColors\Tables\StatusResultaatColorsTable;
use App\Models\StatusResultaatColor;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

covers(
    StatusResultaatColorResource::class,
    ListStatusResultaatColors::class,
    CreateStatusResultaatColor::class,
    EditStatusResultaatColor::class,
    StatusResultaatColorForm::class,
    StatusResultaatColorsTable::class,
);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($this->admin);

    // Remove the seeded default colors so tests fully control the configured combinations.
    StatusResultaatColor::query()->delete();
});

test('admin can view status resultaat color list', function () {
    $colors = StatusResultaatColor::factory()->count(3)->create();

    livewire(ListStatusResultaatColors::class)
        ->assertOk()
        ->assertCanSeeTableRecords($colors);
});

test('admin can create a status resultaat color', function () {
    livewire(CreateStatusResultaatColor::class)
        ->fillForm([
            'status_name' => 'Afgerond',
            'resultaat' => 'Verleend',
            'color' => '#22C55E',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    expect(StatusResultaatColor::where('status_name', 'Afgerond')->where('resultaat', 'Verleend')->first())
        ->color->toBe('#22C55E');
});

test('admin cannot create a duplicate status and resultaat combination', function () {
    StatusResultaatColor::factory()->create([
        'status_name' => 'Afgerond',
        'resultaat' => 'Verleend',
        'color' => '#22C55E',
    ]);

    livewire(CreateStatusResultaatColor::class)
        ->fillForm([
            'status_name' => 'Afgerond',
            'resultaat' => 'Verleend',
            'color' => '#000000',
        ])
        ->call('create')
        ->assertHasFormErrors(['resultaat' => 'unique']);
});

test('admin can update a status resultaat color', function () {
    $color = StatusResultaatColor::factory()->create([
        'status_name' => 'Ontvangen',
        'resultaat' => null,
        'color' => '#3B82F6',
    ]);

    livewire(EditStatusResultaatColor::class, ['record' => $color->getKey()])
        ->fillForm(['color' => '#000000'])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    expect($color->refresh()->color)->toBe('#000000');
});

test('admin can delete a status resultaat color', function () {
    $color = StatusResultaatColor::factory()->create();

    livewire(EditStatusResultaatColor::class, ['record' => $color->getKey()])
        ->callAction('delete');

    expect(StatusResultaatColor::find($color->getKey()))->toBeNull();
});
