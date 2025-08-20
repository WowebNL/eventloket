<?php

use App\Enums\Role;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->municipalities = Municipality::factory()->count(3)->create();

    $this->advisory = Advisory::factory()->create([
        'name' => 'Test Advisory Service',
    ]);

    $this->advisor = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisory->users()->attach($this->advisor);
});

test('advisory can be linked to multiple municipalities', function () {
    // Attach advisory to municipalities
    $this->advisory->municipalities()->attach($this->municipalities->pluck('id'));

    expect($this->advisory->municipalities)->toHaveCount(3)
        ->and($this->advisory->municipalities->pluck('id'))->toContain($this->municipalities[0]->id)
        ->and($this->advisory->municipalities->pluck('id'))->toContain($this->municipalities[1]->id)
        ->and($this->advisory->municipalities->pluck('id'))->toContain($this->municipalities[2]->id);
});

test('advisor users can access advisory they belong to as a tenant', function () {
    // Arrange
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisor);

    // Act
    $response = $this->get(route('filament.advisor.pages.dashboard', ['tenant' => $this->advisory->id]));

    // Assert
    $response->assertSuccessful();
});

test('advisor users cannot access advisory they do not belong to', function () {
    // Arrange
    Filament::setCurrentPanel(Filament::getPanel('advisor'));

    $otherAdvisory = Advisory::factory()->create(['name' => 'Other Advisory']);

    $this->actingAs($this->advisor);

    // Act - try to access advisory the user doesn't belong to
    $response = $this->get(route('filament.advisor.pages.dashboard', ['tenant' => $otherAdvisory->id]));

    // Assert
    $response->assertNotFound();
});
