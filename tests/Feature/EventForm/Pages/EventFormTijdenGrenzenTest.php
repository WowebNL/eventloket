<?php

declare(strict_types=1);

/**
 * The build-up and take-down pickers must stay usable after the event itself
 * moves. A copied application (or a draft that is edited later) carries the
 * old build-up and take-down period; once the organiser moves the event to a
 * later date, both pickers must still allow a date in the new period.
 */

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-13 10:00:00');

    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->actingAs($this->user);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('organiser'));
    Filament\Facades\Filament::setTenant($this->organisation);
});

/**
 * Reads the `min` or `max` attribute of the native datetime input bound to the
 * given field from the rendered page.
 */
function grensVan(string $html, string $veld, string $attribuut): ?string
{
    $pattern = '/<input\b[^>]*wire:model(?:\.live)?="data\.'.preg_quote($veld, '/').'"[^>]*>/s';
    if (! preg_match($pattern, $html, $input)) {
        throw new RuntimeException("No input rendered for {$veld}");
    }

    return preg_match('/\s'.$attribuut.'="([^"]*)"/', $input[0], $m) ? $m[1] : null;
}

function kopieVanEerdereAanvraag(User $user, Organisation $organisation): Draft
{
    // What "reuse" produces: a new draft whose values are the source case's
    // values, dates included.
    return Draft::create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'state' => ['values' => [
            'EvenementStart' => '2027-01-11 15:00:00',
            'EvenementEind' => '2027-01-11 16:00:00',
            'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Ja',
            'OpbouwStart' => '2027-01-07 16:00:00',
            'OpbouwEind' => '2027-01-10 18:00:00',
            'zijnErAansluitendAanHetEvenementAfbouwactiviteiten' => 'Ja',
            'AfbouwStart' => '2027-01-11 17:00:00',
            'AfbouwEind' => '2027-01-12 12:00:00',
        ], 'system' => []],
        'current_step_key' => null,
    ]);
}

test('copied application: build-up start can move into the new period after the event moves later', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => kopieVanEerdereAanvraag($this->user, $this->organisation)->id]);

    $component->set('data.EvenementStart', '2027-03-01 15:00:00');
    $component->set('data.EvenementEind', '2027-03-01 16:00:00');

    $max = grensVan($component->html(), 'OpbouwStart', 'max');

    // The organiser wants to start building up on 27 February.
    expect($max === null || $max >= '2027-02-27 08:00:00')->toBeTrue("OpbouwStart max is {$max}");
});

test('copied application: take-down start can move into the new period after the event moves later', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => kopieVanEerdereAanvraag($this->user, $this->organisation)->id]);

    $component->set('data.EvenementStart', '2027-03-01 15:00:00');
    $component->set('data.EvenementEind', '2027-03-01 16:00:00');

    $html = $component->html();
    $min = grensVan($html, 'AfbouwStart', 'min');
    $max = grensVan($html, 'AfbouwStart', 'max');

    expect($max === null || $max >= $min)->toBeTrue("AfbouwStart min {$min}, max {$max}");
    expect($max === null || $max >= '2027-03-01 17:00:00')->toBeTrue("AfbouwStart max is {$max}");
});

test('copied application: build-up and take-down ends can move into the new period after the event moves earlier', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => kopieVanEerdereAanvraag($this->user, $this->organisation)->id]);

    // The event moves from 11 January 2027 to 1 December 2026, before the
    // copied build-up and take-down periods.
    $component->set('data.EvenementStart', '2026-12-01 15:00:00');
    $component->set('data.EvenementEind', '2026-12-01 16:00:00');

    $html = $component->html();

    // The organiser wants to build up until 30 November 18:00.
    $opbouwMin = grensVan($html, 'OpbouwEind', 'min');
    $opbouwMax = grensVan($html, 'OpbouwEind', 'max');
    expect($opbouwMin === null || $opbouwMin <= '2026-11-30 18:00:00')->toBeTrue("OpbouwEind min is {$opbouwMin}");
    expect($opbouwMin === null || $opbouwMax === null || $opbouwMin <= $opbouwMax)
        ->toBeTrue("OpbouwEind min {$opbouwMin}, max {$opbouwMax}");

    // And to take down until 2 December 12:00.
    $afbouwMin = grensVan($html, 'AfbouwEind', 'min');
    $afbouwMax = grensVan($html, 'AfbouwEind', 'max');
    expect($afbouwMin === null || $afbouwMin <= '2026-12-02 12:00:00')->toBeTrue("AfbouwEind min is {$afbouwMin}");
    expect($afbouwMin === null || $afbouwMax === null || $afbouwMin <= $afbouwMax)
        ->toBeTrue("AfbouwEind min {$afbouwMin}, max {$afbouwMax}");
});

test('new application: build-up start can move along when the event is moved later', function () {
    $draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => ['values' => [], 'system' => []],
        'current_step_key' => null,
    ]);
    $component = Livewire::test(EventFormPage::class, ['draft' => $draft->id]);

    $component->set('data.EvenementStart', '2027-01-11 15:00:00');
    $component->set('data.EvenementEind', '2027-01-11 16:00:00');
    $component->set('data.zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Ja');
    $component->set('data.OpbouwStart', '2027-01-07 16:00:00');
    $component->set('data.OpbouwEind', '2027-01-10 18:00:00');

    $component->set('data.EvenementStart', '2027-03-01 15:00:00');
    $component->set('data.EvenementEind', '2027-03-01 16:00:00');

    $max = grensVan($component->html(), 'OpbouwStart', 'max');

    expect($max === null || $max >= '2027-02-27 08:00:00')->toBeTrue("OpbouwStart max is {$max}");
});

test('the build-up day rows follow the build-up period, not the event', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => kopieVanEerdereAanvraag($this->user, $this->organisation)->id]);

    $component->set('data.EvenementStart', '2027-03-01 15:00:00');
    $component->set('data.EvenementEind', '2027-03-01 16:00:00');

    // Moving the event leaves the old build-up rows in place.
    expect(array_keys($component->get('data.OpbouwDagen')))
        ->toBe(['2027-01-07', '2027-01-08', '2027-01-09', '2027-01-10']);

    // Once the build-up period itself moves (end first, which the current
    // bounds allow), the rows are rebuilt for the new days.
    $component->set('data.OpbouwEind', '2027-02-28 18:00:00');
    $component->set('data.OpbouwStart', '2027-02-27 08:00:00');

    expect(array_keys($component->get('data.OpbouwDagen')))
        ->toBe(['2027-02-27', '2027-02-28']);
});

/**
 * Runs the field rules the form registers for the given Tijden fields against
 * the current state, and returns the error bag.
 *
 * @return array<string, list<string>>
 */
function tijdenFouten(EventFormPage $page, string ...$velden): array
{
    $rules = [];
    foreach ($velden as $veld) {
        $field = $page->form->getComponent(
            fn ($component): bool => $component instanceof Field && $component->getName() === $veld,
            withHidden: true,
        );
        $rules["data.{$veld}"] = $field->getValidationRules();
    }

    return Validator::make(['data' => $page->data], $rules)->errors()->toArray();
}

test('the order within the build-up and take-down periods is still enforced', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => kopieVanEerdereAanvraag($this->user, $this->organisation)->id]);

    $component->set('data.EvenementStart', '2027-03-01 15:00:00');
    $component->set('data.EvenementEind', '2027-03-01 16:00:00');

    // Both starts move into the new period while the ends still hold the old dates.
    $component->set('data.OpbouwStart', '2027-02-27 08:00:00');
    $component->set('data.AfbouwStart', '2027-03-01 17:00:00');

    expect(tijdenFouten($component->instance(), 'OpbouwStart', 'AfbouwEind'))
        ->toHaveKeys(['data.OpbouwStart', 'data.AfbouwEind']);

    // Once the ends follow, both periods are valid again.
    $component->set('data.OpbouwEind', '2027-02-28 18:00:00');
    $component->set('data.AfbouwEind', '2027-03-02 12:00:00');

    expect(tijdenFouten($component->instance(), 'OpbouwStart', 'OpbouwEind', 'AfbouwStart', 'AfbouwEind'))
        ->toBe([]);
});
