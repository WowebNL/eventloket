<?php

declare(strict_types=1);

/**
 * Choosing a structure type must force at least one row.
 *
 * The "Bouwsels > 10m²" fieldset asks which structures are placed on the
 * location, and each answer reveals its own repeater. A repeater without a
 * minimum accepts zero rows, so the applicant could tick "Tent(en)" and walk
 * past the step without describing a single tent. The step then validates, and
 * the answer arrives empty.
 *
 * Both sides of the invariant are checked, because a minimum that is applied
 * too widely is as wrong as one that is missing:
 *
 *   - a repeater whose option IS ticked must reject an empty list;
 *   - a repeater whose option is NOT ticked is hidden and must not contribute
 *     any rule at all, so it can never block a step it has nothing to do with.
 *
 * Validation is driven through the real step schema on a real page, so the
 * assertions rest on the rules Filament actually collects rather than on the
 * builder call that produces them.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Schema\Steps\VergunningaanvraagVervolgvragenStep;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** The answer that reveals the "Bouwsels > 10m²" fieldset. */
const BOUWSELS_APPLICABLE = ['kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A3' => true]];

/** Option value per repeater, as the checkbox list stores it. */
const BOUWSEL_OPTIONS = [
    'tenten' => 'A54',
    'podia' => 'A55',
    'overkappingen' => 'A56',
];

beforeEach(function () {
    $this->user = User::factory()->state(['role' => Role::Organiser])->create();
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach(
        $this->organisation->id,
        ['role' => OrganisationRole::Admin->value],
    );

    $this->actingAs($this->user);
    Filament::setTenant($this->organisation);
});

/**
 * The step under test, on a page that holds the given answers.
 *
 * @param  array<string, mixed>  $values
 */
function bouwselsStepSchema(User $user, Organisation $organisation, array $values): Schema
{
    $draft = Draft::create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'state' => ['values' => $values, 'system' => []],
    ]);

    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $draft->id])->instance();

    $page->data = array_merge($page->data ?? [], $values);
    $page->state()->absorbFields($values);

    $form = $page->getSchema('form');

    /** @var Component $wizard */
    $wizard = $form->getComponents(withHidden: true)[0];

    foreach ($wizard->getChildSchema()->getComponents(withHidden: true) as $step) {
        $key = (string) $step->getKey();

        if (! str_ends_with($key, VergunningaanvraagVervolgvragenStep::UUID)) {
            continue;
        }

        return $step->getChildSchema();
    }

    throw new RuntimeException('The step under test is not part of the form schema.');
}

/**
 * The keys the step rejects, relative to the form state path.
 *
 * @param  array<string, mixed>  $values
 * @return list<string>
 */
function bouwselsRejectedKeys(User $user, Organisation $organisation, array $values): array
{
    $schema = bouwselsStepSchema($user, $organisation, $values);
    $prefix = $schema->getLivewire()->getSchema('form')->getStatePath().'.';

    try {
        $schema->validate();
    } catch (ValidationException $exception) {
        return array_values(array_map(
            fn (string $key): string => str_starts_with($key, $prefix)
                ? substr($key, strlen($prefix))
                : $key,
            array_keys($exception->errors()),
        ));
    }

    return [];
}

test('a ticked structure type rejects an empty repeater', function (string $repeater, string $option) {
    $rejected = bouwselsRejectedKeys($this->user, $this->organisation, [
        ...BOUWSELS_APPLICABLE,
        'watVoorBouwselsPlaatsUOpDeLocaties' => [$option => true],
        $repeater => [],
    ]);

    expect($rejected)->toContain($repeater);
})->with([
    'tenten' => ['tenten', 'A54'],
    'podia' => ['podia', 'A55'],
    'overkappingen' => ['overkappingen', 'A56'],
]);

test('a ticked structure type accepts a filled repeater', function (string $repeater, string $option) {
    $rejected = bouwselsRejectedKeys($this->user, $this->organisation, [
        ...BOUWSELS_APPLICABLE,
        'watVoorBouwselsPlaatsUOpDeLocaties' => [$option => true],
        $repeater => ['row-1' => []],
    ]);

    expect($rejected)->not->toContain($repeater);
})->with([
    'tenten' => ['tenten', 'A54'],
    'podia' => ['podia', 'A55'],
    'overkappingen' => ['overkappingen', 'A56'],
]);

test('an unticked structure type never blocks the step', function (string $repeater, string $option) {
    // Every other option is ticked, so the step is in use and the repeater
    // under test is the only one that is hidden.
    $others = array_fill_keys(
        array_values(array_diff(BOUWSEL_OPTIONS, [$option])),
        true,
    );

    $rejected = bouwselsRejectedKeys($this->user, $this->organisation, [
        ...BOUWSELS_APPLICABLE,
        'watVoorBouwselsPlaatsUOpDeLocaties' => $others,
        ...array_fill_keys(array_keys(array_diff(BOUWSEL_OPTIONS, [$option])), ['row-1' => []]),
        $repeater => [],
    ]);

    expect($rejected)->not->toContain($repeater);
})->with([
    'tenten' => ['tenten', 'A54'],
    'podia' => ['podia', 'A55'],
    'overkappingen' => ['overkappingen', 'A56'],
]);

test('no structure type at all leaves all three repeaters out of validation', function () {
    $rejected = bouwselsRejectedKeys($this->user, $this->organisation, BOUWSELS_APPLICABLE);

    expect($rejected)->not->toContain('tenten')
        ->and($rejected)->not->toContain('podia')
        ->and($rejected)->not->toContain('overkappingen');
});
