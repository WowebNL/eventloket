<?php

declare(strict_types=1);

/**
 * Walks the whole form instead of naming fields.
 *
 * The leak this covers is not a property of one field, it is a property of the
 * state layer, so a test that lists the fields it knows about proves nothing
 * about the next conditional field somebody adds. This walk asks the schema
 * itself which fields exist and which of them the organiser is actually being
 * asked, puts a recognisable value on every field that is not being asked, and
 * then requires that none of those values survive anywhere.
 *
 * Both sides of the invariant are checked, because a prune that is too eager
 * is as wrong as one that is too careful:
 *
 *   - a field the organiser cannot answer must be gone from the state and from
 *     all four outputs;
 *   - a field the organiser can answer must keep the value it had.
 *
 * The expectation is worked out on a second, identical page, so the page under
 * test cannot hand the test its own answer through Filament's per-schema
 * caches.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormDerivedState;
use App\EventForm\State\FormState;
use App\EventForm\Submit\MapFormStateToReferenceData;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use Dotswan\MapPicker\Fields\Map;
use Filament\Facades\Filament;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** A moment no scenario in this file uses, so it is recognisable in an output. */
const WALK_STALE_DATE = '2035-06-15 12:00';

const WALK_STALE_YEAR = '2035-06-15';

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
 * A page on its own draft, with the given answers already in place.
 *
 * @param  array<string, mixed>  $values
 */
function walkPage(User $user, Organisation $organisation, array $values): EventFormPage
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
    $page->state()->setSystem('authUser', OrganiserUser::find($user->id));
    $page->state()->setSystem('authOrganisation', $organisation);

    return $page;
}

/**
 * A recognisable value for a field, in the shape that field holds.
 *
 * The shapes are read off the component class rather than off a list of field
 * names, so a new conditional field is covered the moment it is added.
 */
function walkMarkerFor(Field $field, string $key): mixed
{
    $marker = 'NIET-GEVRAAGD-'.$key;

    if ($field instanceof Repeater) {
        return ['walk-row' => ['walkMarker' => $marker]];
    }

    if ($field instanceof CheckboxList || $field instanceof BaseFileUpload) {
        return [$marker];
    }

    if ($field instanceof Select && $field->isMultiple()) {
        return [$marker];
    }

    if ($field instanceof Map) {
        return ['lat' => 0.0, 'lng' => 0.0, 'walkMarker' => $marker];
    }

    if ($field instanceof DateTimePicker) {
        // A date field cannot hold the marker, so it gets a date no scenario
        // uses; the assertions look for that date as well.
        return WALK_STALE_DATE;
    }

    if ($field instanceof Checkbox) {
        // Nothing recognisable fits in a boolean; the key-level assertion
        // still covers it.
        return true;
    }

    return $marker;
}

/**
 * Which top-level state keys the wizard owns, and which of those the organiser
 * is being asked right now: the field is visible and it sits on a step the
 * current answers apply to.
 *
 * @return array{owned: list<string>, answerable: list<string>, fields: array<string, Field>}
 */
function walkSchemaKeys(EventFormPage $page): array
{
    $form = $page->getSchema('form');

    $wizard = $form->getComponents(withHidden: true)[0] ?? null;
    expect($wizard)->toBeInstanceOf(Component::class);

    $prefix = $form->getStatePath().'.';

    /** @param array<string, Field> $fields */
    $keysOf = function (array $fields) use ($prefix): array {
        $keys = [];
        foreach ($fields as $field) {
            $path = (string) $field->getStatePath();
            if (! str_starts_with($path, $prefix)) {
                continue;
            }
            $path = substr($path, strlen($prefix));
            if ($path === '' || str_contains($path, '.')) {
                continue;
            }
            $keys[$path] = $field;
        }

        return $keys;
    };

    $owned = [];
    $answerable = [];

    foreach ($wizard->getChildSchema()->getComponents(withHidden: true) as $step) {
        $key = (string) $step->getKey();
        $uuid = str_starts_with($key, 'form.') ? substr($key, 5) : $key;

        $owned = [...$owned, ...$keysOf($step->getChildSchema()->getFlatFields(withHidden: true))];

        if (! $page->state()->isStepApplicable($uuid)) {
            continue;
        }

        $answerable = [...$answerable, ...$keysOf($step->getChildSchema()->getFlatFields(withHidden: false))];
    }

    // Keys the server computes are not answers and are never pruned; they are
    // out of scope for this walk.
    $vanDeServer = array_flip([...ServiceFetcher::FETCHED_VARIABLES, ...array_keys(FormDerivedState::COMPUTED_KEYS)]);

    $owned = array_diff_key($owned, $vanDeServer);
    $answerable = array_diff_key($answerable, $vanDeServer);

    return [
        'owned' => array_keys($owned),
        'answerable' => array_keys($answerable),
        'fields' => $owned,
    ];
}

/**
 * Everything the application produces from this state, as one blob of JSON.
 */
function walkOutputs(FormState $state): string
{
    return json_encode([
        app(ZaakeigenschappenMap::class)->buildEigenschappen($state),
        app(MapFormStateToReferenceData::class)
            ->build($state, 'Ingediend', 'https://zgw.example.com/statustypen/1')
            ->toArray(),
        $state->toSnapshot()['values'],
        app(SubmissionReport::class)->build($state, EventFormSchema::stepsForReport()),
    ], JSON_THROW_ON_ERROR);
}

/**
 * The walk itself. Fills every field the organiser is not being asked with a
 * value that names the field, submits the state through the same two calls
 * `submit()` makes, and then requires those values to be gone everywhere while
 * the answers that were being asked are untouched.
 *
 * @param  array<string, mixed>  $antwoorden
 */
function loopHetSchemaAf(User $user, Organisation $organisation, array $antwoorden): void
{
    $verkenning = walkSchemaKeys(walkPage($user, $organisation, $antwoorden));

    $nietGevraagd = array_values(array_diff($verkenning['owned'], $verkenning['answerable']));
    expect($nietGevraagd)->not->toBeEmpty();

    $vervuild = $antwoorden;
    foreach ($nietGevraagd as $key) {
        if (array_key_exists($key, $antwoorden)) {
            continue;
        }
        $vervuild[$key] = walkMarkerFor($verkenning['fields'][$key], $key);
    }

    // A second page for the expectation, so the page under test evaluates its
    // own visibility from scratch.
    $verwachting = walkSchemaKeys(walkPage($user, $organisation, $vervuild));
    $nietGevraagd = array_values(array_diff($verwachting['owned'], $verwachting['answerable']));

    $page = walkPage($user, $organisation, $vervuild);

    $form = $page->getSchema('form');
    $dehydrationState = [];
    $form->callBeforeStateDehydrated($dehydrationState);

    $absorb = new ReflectionMethod($page, 'absorbFormData');
    $absorb->setAccessible(true);
    $absorb->invoke($page, $form->getStateSnapshot());

    $voor = $page->state()->fields();

    $prune = new ReflectionMethod($page, 'pruneStateToVisible');
    $prune->setAccessible(true);
    $prune->invoke($page);

    $na = $page->state();
    $uitgaand = walkOutputs($na);

    $blijvenHangen = array_values(array_intersect($nietGevraagd, array_keys($na->fields())));
    expect($blijvenHangen)->toBe([], 'fields that are not being asked survived the prune: '.implode(', ', $blijvenHangen));

    expect(str_contains($uitgaand, 'NIET-GEVRAAGD-'))->toBeFalse('a value of a field that is not being asked reached one of the outputs')
        ->and(str_contains($uitgaand, WALK_STALE_YEAR))->toBeFalse('a date of a field that is not being asked reached one of the outputs');

    $verdwenen = [];
    foreach ($verwachting['answerable'] as $key) {
        if (array_key_exists($key, $voor) && ! array_key_exists($key, $na->fields())) {
            $verdwenen[] = $key;
        }
    }
    expect($verdwenen)->toBe([], 'answers that are being asked were pruned: '.implode(', ', $verdwenen));
}

test('no field of a vooraankondiging keeps an answer it is not asked for', function () {
    loopHetSchemaAf($this->user, $this->organisation, [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
        'waarVindtHetEvenementPlaats' => ['gebouw'],
    ]);
});

test('no field of a melding keeps an answer it is not asked for', function () {
    loopHetSchemaAf($this->user, $this->organisation, [
        'waarvoorWiltUEventloketGebruiken' => 'aanvraag',
        'waarVindtHetEvenementPlaats' => ['gebouw'],
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Ja',
        'vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen' => 'Ja',
        'WordtErAlleenMuziekGeluidGeproduceerdTussen' => 'Ja',
        'IsdeGeluidsproductieLagerDan' => 'Ja',
        'erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten' => 'Ja',
        'wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst' => 'Ja',
        'indienErObjectenGeplaatstWordenZijnDezeDanKleiner' => 'Ja',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);
});

test('no field of a permit application keeps an answer it is not asked for', function () {
    loopHetSchemaAf($this->user, $this->organisation, [
        'waarvoorWiltUEventloketGebruiken' => 'aanvraag',
        'waarVindtHetEvenementPlaats' => ['buiten'],
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Nee',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);
});

test('no field of a route event keeps an answer it is not asked for', function () {
    loopHetSchemaAf($this->user, $this->organisation, [
        'waarvoorWiltUEventloketGebruiken' => 'aanvraag',
        'waarVindtHetEvenementPlaats' => ['route'],
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Ja',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);
});
