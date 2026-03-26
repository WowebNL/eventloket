<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Livewire\Zaken\DeelzakenTable;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->organiser = User::factory()->create([
        'role' => Role::Organiser,
    ]);

    $this->organisation = Organisation::factory()->create([
        'type' => 'business',
    ]);

    $this->organisation->users()->attach($this->organiser, [
        'role' => OrganisationRole::Admin,
    ]);

    $this->zaaktype = Zaaktype::factory()->create();
});

test('renders deelzaken table for a zaak', function () {
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    $this->actingAs($this->organiser);

    livewire(DeelzakenTable::class, ['zaak' => $zaak])
        ->assertOk();
});

test('shows deelzaken that share the same data_object_url', function () {
    ZgwHttpFake::wildcardFake();

    $sharedUrl = 'https://objects.example.com/api/v2/objects/shared-object-1';

    $hoofdzaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'data_object_url' => $sharedUrl,
    ]);

    $deelzaakType = Zaaktype::factory()->create(['name' => 'Doorkomst vergunning']);

    $deelzaak = Zaak::factory()->create([
        'zaaktype_id' => $deelzaakType->id,
        'organisation_id' => $this->organisation->id,
        'data_object_url' => $sharedUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(DeelzakenTable::class, ['zaak' => $hoofdzaak])
        ->assertOk()
        ->assertCountTableRecords(1);
});

test('does not show the zaak itself as a deelzaak', function () {
    ZgwHttpFake::wildcardFake();

    $sharedUrl = 'https://objects.example.com/api/v2/objects/shared-object-2';

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'data_object_url' => $sharedUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(DeelzakenTable::class, ['zaak' => $zaak])
        ->assertOk()
        ->assertCountTableRecords(0);
});

test('shows multiple deelzaken for the same data_object_url', function () {
    ZgwHttpFake::wildcardFake();

    $sharedUrl = 'https://objects.example.com/api/v2/objects/shared-object-3';

    $hoofdzaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'data_object_url' => $sharedUrl,
    ]);

    $deelzaakType = Zaaktype::factory()->create();

    Zaak::factory()->count(3)->create([
        'zaaktype_id' => $deelzaakType->id,
        'organisation_id' => $this->organisation->id,
        'data_object_url' => $sharedUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(DeelzakenTable::class, ['zaak' => $hoofdzaak])
        ->assertOk()
        ->assertCountTableRecords(3);
});

test('does not show zaken with a different data_object_url', function () {
    ZgwHttpFake::wildcardFake();

    $hoofdzaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'data_object_url' => 'https://objects.example.com/api/v2/objects/object-a',
    ]);

    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'data_object_url' => 'https://objects.example.com/api/v2/objects/object-b',
    ]);

    $this->actingAs($this->organiser);

    livewire(DeelzakenTable::class, ['zaak' => $hoofdzaak])
        ->assertOk()
        ->assertCountTableRecords(0);
});
