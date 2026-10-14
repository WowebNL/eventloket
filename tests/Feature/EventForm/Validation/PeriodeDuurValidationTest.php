<?php

declare(strict_types=1);

/**
 * A period on the Tijden step may not run longer than EventDagen::MAX_DAGEN
 * calendar days. EventDagen bounds its own day list at that number, but a bound
 * on its own would clip the period silently. These tests guard the other half:
 * the organiser gets a message on the end date field instead.
 */

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\Support\EventDagen;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->actingAs($this->user);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('organiser'));
    Filament\Facades\Filament::setTenant($this->organisation);

    $this->draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => ['values' => [], 'system' => []],
        'current_step_key' => null,
    ]);
});

/**
 * Validate only the Tijden step of a live page, the same way the page itself
 * validates a step, and return the messages per field. Validating the whole
 * form would stop at the first incomplete step and never reach this one.
 *
 * @return array<string, list<string>>
 */
function tijdenStepErrors(EventFormPage $page): array
{
    $schema = $page->getSchema('form');
    expect($schema)->not->toBeNull();

    $wizard = $schema->getComponents(withHidden: true)[0];

    $tijden = null;
    foreach ($wizard->getChildSchema()->getComponents(withHidden: true) as $step) {
        /** @var Component $step */
        $key = (string) $step->getKey();
        $uuid = str_starts_with($key, 'form.') ? substr($key, 5) : $key;

        if ($uuid === TijdenStep::UUID) {
            $tijden = $step;
            break;
        }
    }

    expect($tijden)->not->toBeNull();

    try {
        $tijden->getChildSchema()->validate();
    } catch (ValidationException $exception) {
        return $exception->validator->errors()->messages();
    }

    return [];
}

test('een einddatum ver buiten het bedoelde bereik geeft een melding op het veld in plaats van een foutpagina', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    $component->set('data.EvenementStart', '2026-07-01 10:00:00');
    $component->set('data.EvenementEind', '9999-12-31 18:00:00');

    $errors = tijdenStepErrors($component->instance());

    expect($errors)->toHaveKey('data.EvenementEind');
    expect($errors['data.EvenementEind'])->toContain(
        'Een periode mag maximaal '.EventDagen::MAX_DAGEN.' dagen beslaan. Controleer de startdatum en de einddatum.',
    );
});

test('een periode van één dag boven het maximum wordt geweigerd', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    $component->set('data.EvenementStart', '2026-07-01 10:00:00');
    $component->set('data.EvenementEind', '2026-09-29 18:00:00');

    expect(tijdenStepErrors($component->instance()))->toHaveKey('data.EvenementEind');
});

test('een periode van precies het maximum aantal dagen komt zonder duurmelding door', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    $component->set('data.EvenementStart', '2026-07-01 10:00:00');
    $component->set('data.EvenementEind', '2026-09-28 18:00:00');

    // Other fields on the step are still empty, so there are other messages;
    // the end date itself must not be one of them.
    expect(tijdenStepErrors($component->instance()))->not->toHaveKey('data.EvenementEind');
});

test('de duurgrens staat op het eindveld van elke periode, niet alleen op het evenement', function (string $eindVeld, string $startVeld) {
    $pickers = findDateTimePickers(TijdenStep::make());

    $reflection = new ReflectionClass($pickers[$eindVeld]);
    $rulesProp = $reflection->getProperty('rules');
    $rulesProp->setAccessible(true);

    $heeftDuurRegel = collect($rulesProp->getValue($pickers[$eindVeld]))->contains(function ($entry) use ($startVeld): bool {
        [$rule] = $entry;

        if (! $rule instanceof Closure) {
            return false;
        }

        return ((new ReflectionFunction($rule))->getStaticVariables()['startKey'] ?? null) === $startVeld;
    });

    expect($heeftDuurRegel)->toBeTrue();
})->with([
    ['EvenementEind', 'EvenementStart'],
    ['OpbouwEind', 'OpbouwStart'],
    ['AfbouwEind', 'AfbouwStart'],
]);
