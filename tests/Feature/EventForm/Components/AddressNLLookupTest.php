<?php

declare(strict_types=1);

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\State\FormState;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    $this->draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => FormState::empty()->toSnapshot(),
        'current_step_key' => null,
    ]);

    // PDOK's free-text search always returns the same real address (house
    // number 1). That mimics its fuzzy behaviour: a non-existent house number
    // still resolves to the nearest real one.
    Http::fake([
        'https://api.pdok.nl/*' => Http::response([
            'response' => [
                'docs' => [[
                    'id' => 'adr-1',
                    'type' => 'adres',
                    'centroide_ll' => 'POINT(5.88 50.91)',
                    'weergavenaam' => 'Deweverplein 1, 6361BZ Nuth',
                    'straatnaam' => 'Deweverplein',
                    'postcode' => '6361BZ',
                    'huisnummer' => 1,
                    'woonplaatsnaam' => 'Nuth',
                    'gemeentecode' => '1954',
                    'gemeentenaam' => 'Beekdaelen',
                ]],
            ],
        ]),
    ]);
});

function seedGebouwRow(Testable $component): string
{
    $component->set('data.waarVindtHetEvenementPlaats', ['gebouw']);
    $rows = $component->get('data.adresVanDeGebouwEn');
    $uuid = array_key_first($rows);

    return "data.adresVanDeGebouwEn.$uuid.adresVanHetGebouwWaarUwEvenementPlaatsvindt1";
}

test('an existing postcode + house number auto-fills street and city', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);
    $base = seedGebouwRow($component);

    $component
        ->set("$base.postcode", '6361 BZ')
        ->set("$base.huisnummer", '1');

    expect($component->get("$base.straatnaam"))->toBe('Deweverplein')
        ->and($component->get("$base.woonplaatsnaam"))->toBe('Nuth');
});

test('a successful lookup stores the resolved BRK gemeente for the address', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);
    $base = seedGebouwRow($component);

    $component
        ->set("$base.postcode", '6361 BZ')
        ->set("$base.huisnummer", '1');

    // Reused by the location gate so it need not do a second PDOK lookup to
    // determine this address's gemeente. 'GM' + the PDOK gemeentecode (1954).
    expect($component->get("$base.brkGemeente"))->toBe('GM1954');
});

test('a failed lookup clears the stored BRK gemeente', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);
    $base = seedGebouwRow($component);

    $component
        ->set("$base.postcode", '6361 BZ')
        ->set("$base.huisnummer", '1');
    expect($component->get("$base.brkGemeente"))->toBe('GM1954');

    $component->set("$base.huisnummer", '999');

    expect($component->get("$base.brkGemeente"))->toBeEmpty();
});

test('changing to a non-existent house number clears the stale street/city and notifies', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);
    $base = seedGebouwRow($component);

    $component
        ->set("$base.postcode", '6361 BZ')
        ->set("$base.huisnummer", '1');

    // Sanity: filled first.
    expect($component->get("$base.straatnaam"))->toBe('Deweverplein');

    // Now change to a house number PDOK does not have (it still returns nr 1).
    $component->set("$base.huisnummer", '999');

    expect($component->get("$base.straatnaam"))->toBeEmpty()
        ->and($component->get("$base.woonplaatsnaam"))->toBeEmpty();

    $component->assertNotified('Geen adres gevonden');
});
