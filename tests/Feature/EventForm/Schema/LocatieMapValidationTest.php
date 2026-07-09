<?php

declare(strict_types=1);

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use App\EventForm\State\FormState;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);
});

/**
 * Validate the Locatie step of a freshly mounted form for the given draft state,
 * and return the validation error messages for the locatieSOpKaart field.
 *
 * @param  array<string, mixed>  $values
 * @return list<string>
 */
function validateLocatieStep(array $values): array
{
    $state = FormState::empty();
    foreach ($values as $key => $value) {
        $state->setField($key, $value);
    }

    $draft = Draft::create([
        'user_id' => test()->user->id,
        'organisation_id' => test()->organisation->id,
        'state' => $state->toSnapshot(),
        'current_step_key' => LocatieVanHetEvenement2Step::UUID,
    ]);

    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $draft->id])->instance();

    $wizard = $page->form->getComponents(withHidden: true)[0];
    $locatieStep = null;
    foreach ($wizard->getChildSchema()->getComponents(withHidden: true) as $step) {
        $key = $step->getKey();
        $uuid = str_starts_with($key, 'form.') ? substr($key, 5) : $key;
        if ($uuid === LocatieVanHetEvenement2Step::UUID) {
            $locatieStep = $step;
        }
    }

    try {
        $locatieStep->getChildSchema()->validate();
    } catch (ValidationException $e) {
        return $e->errors()['data.locatieSOpKaart'] ?? [];
    }

    return [];
}

function polygonMapState(array $ring): array
{
    return [
        'lat' => 50.85,
        'lng' => 5.69,
        'geojson' => [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
            ]],
        ],
    ];
}

test('buiten selected with only the placeholder (no drawn shape) blocks the step', function () {
    // Selecting "buiten" seeds locatieSOpKaart with a non-empty placeholder,
    // which fooled ->required(). This is the reported scenario.
    $errors = validateLocatieStep([
        'waarVindtHetEvenementPlaats' => ['buiten'],
        'naamVanDeLocatieKaart' => 'Testlocatie',
        'locatieSOpKaart' => ['6dd157f3-f46e-4465-b4aa-5916da0c6b4f' => []],
    ]);

    expect($errors)->toContain('Teken een volledige vorm op de kaart voordat u verder gaat.');
});

test('buiten selected with an incomplete polygon blocks the step', function () {
    $errors = validateLocatieStep([
        'waarVindtHetEvenementPlaats' => ['buiten'],
        'naamVanDeLocatieKaart' => 'Testlocatie',
        'locatieSOpKaart' => polygonMapState([[5.69, 50.85], [5.70, 50.85], [5.69, 50.85]]),
    ]);

    expect($errors)->toContain('De op de kaart getekende vorm is niet compleet. Maak de tekening af of verwijder hem voordat u verder gaat.');
});

test('buiten selected with a complete polygon passes the map validation', function () {
    $errors = validateLocatieStep([
        'waarVindtHetEvenementPlaats' => ['buiten'],
        'naamVanDeLocatieKaart' => 'Testlocatie',
        'locatieSOpKaart' => polygonMapState([[5.69, 50.85], [5.70, 50.85], [5.70, 50.86], [5.69, 50.86], [5.69, 50.85]]),
    ]);

    expect($errors)->toBe([]);
});
