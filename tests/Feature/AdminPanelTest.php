<?php

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->municipality1 = Municipality::factory()->create(['name' => 'Municipality 1']);
    $this->municipality2 = Municipality::factory()->create(['name' => 'Municipality 2']);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $this->reviewer = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::Reviewer,
    ]);

    $this->municipality1->users()->attach($this->reviewer);
});

test('admin users can access any municipality', function () {
    $this->actingAs($this->admin);

    // Check if admin can access municipality 1
    $response = $this->get(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality1]));
    $response->assertSuccessful();

    // Check if admin can access municipality 2
    $response = $this->get(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality2]));
    $response->assertSuccessful();
});

test('reviewer users can only access assigned municipalities', function () {
    $this->actingAs($this->reviewer);

    // Check if reviewer can access assigned municipality
    $response = $this->get(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality1]));
    $response->assertSuccessful();

    // Check if reviewer cannot access unassigned municipality
    $response = $this->get(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality2]));
    $response->assertNotFound();
});

test('admin users can see the municipality switcher', function () {
    $this->actingAs($this->admin);

    Filament::setTenant($this->municipality1);

    $response = $this->get(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality1]));
    $response->assertSuccessful();
    $response->assertSee($this->municipality1->name);
    $response->assertSee($this->municipality2->name);
});

test('reviewer users only see assigned municipalities in switcher', function () {
    $this->actingAs($this->reviewer);

    Filament::setTenant($this->municipality1);

    $response = $this->get(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality1]));
    $response->assertSuccessful();
    $response->assertSee($this->municipality1->name);
    $response->assertDontSee($this->municipality2->name);
});
