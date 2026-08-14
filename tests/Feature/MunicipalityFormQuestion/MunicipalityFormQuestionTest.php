<?php

use App\Enums\MunicipalityFormQuestionType;
use App\Enums\Role;
use App\EventForm\Services\MunicipalityVariablesService;
use App\EventForm\Submit\DetermineAanvraagType;
use App\Exceptions\MunicipalityFormQuestionLimitReached;
use App\Filament\Admin\Resources\MunicipalityResource\Pages\EditMunicipality;
use App\Filament\Admin\Resources\MunicipalityResource\RelationManagers\MunicipalityFormQuestionsRelationManager;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\MunicipalityFormQuestionResource;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages\CreateMunicipalityFormQuestion;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages\EditMunicipalityFormQuestion;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages\ListMunicipalityFormQuestions;
use App\Models\Municipality;
use App\Models\MunicipalityFormQuestion;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/**
 * Log in als gemeentelijk beheerder van deze gemeente en zet het
 * gemeentepaneel + de tenant klaar.
 */
function actAsMunicipalityAdmin(Municipality $municipality): User
{
    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    return $user;
}

// ---------------------------------------------------------------------------
// Datamodel en observer
// ---------------------------------------------------------------------------

test('een nieuwe gemeente begint zonder aanvullende vragen', function () {
    // Anders dan de meldingvragen worden deze niet geseed: de functie is optioneel.
    expect(Municipality::factory()->create()->formQuestions()->count())->toBe(0);
});

test('de volgorde wordt automatisch op de eerstvolgende positie gezet', function () {
    $municipality = Municipality::factory()->create();

    $first = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 0]);
    $second = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 0]);

    expect($first->order)->toBe(1)
        ->and($second->order)->toBe(2);
});

test('opties worden gewist bij een vraagtype dat er geen heeft', function () {
    $question = MunicipalityFormQuestion::factory()->create([
        'type' => MunicipalityFormQuestionType::Text,
        'options' => ['Ja', 'Nee'],
    ]);

    expect($question->fresh()->options)->toBeNull();
});

test('een lege padselectie wordt als null opgeslagen', function () {
    $question = MunicipalityFormQuestion::factory()->create(['show_for_aanvraag_types' => []]);

    expect($question->fresh()->show_for_aanvraag_types)->toBeNull();
});

// ---------------------------------------------------------------------------
// Maximum per gemeente
// ---------------------------------------------------------------------------

test('de vraag boven het maximum wordt geweigerd, ook buiten de knop om', function () {
    config()->set('extra-questions.max_per_municipality', 3);

    $municipality = Municipality::factory()->create();
    MunicipalityFormQuestion::factory()->count(3)->create(['municipality_id' => $municipality->id, 'order' => 0]);

    expect(fn () => MunicipalityFormQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 0,
    ]))->toThrow(MunicipalityFormQuestionLimitReached::class);

    expect($municipality->formQuestions()->count())->toBe(3);
});

test('het maximum van de ene gemeente blokkeert de andere niet', function () {
    config()->set('extra-questions.max_per_municipality', 1);

    $one = Municipality::factory()->create();
    $other = Municipality::factory()->create();
    MunicipalityFormQuestion::factory()->create(['municipality_id' => $one->id, 'order' => 0]);

    $created = MunicipalityFormQuestion::factory()->create(['municipality_id' => $other->id, 'order' => 0]);

    expect($created->exists)->toBeTrue();
});

test('de create-pagina is niet bereikbaar zodra het maximum bereikt is', function () {
    config()->set('extra-questions.max_per_municipality', 2);

    $municipality = Municipality::factory()->create();
    actAsMunicipalityAdmin($municipality);

    expect(MunicipalityFormQuestionResource::canCreate())->toBeTrue();

    MunicipalityFormQuestion::factory()->count(2)->create(['municipality_id' => $municipality->id, 'order' => 0]);

    expect(MunicipalityFormQuestionResource::canCreate())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Policy en tenant-isolatie
// ---------------------------------------------------------------------------

test('een applicatiebeheerder mag alles', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $question = MunicipalityFormQuestion::factory()->create();

    expect($admin->can('viewAny', MunicipalityFormQuestion::class))->toBeTrue()
        ->and($admin->can('create', MunicipalityFormQuestion::class))->toBeTrue()
        ->and($admin->can('view', $question))->toBeTrue()
        ->and($admin->can('update', $question))->toBeTrue()
        ->and($admin->can('delete', $question))->toBeTrue();
});

test('een gemeentelijk beheerder mag de vragen van de eigen gemeente beheren', function () {
    $municipality = Municipality::factory()->create();
    $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $question = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id]);

    expect($user->can('create', MunicipalityFormQuestion::class))->toBeTrue()
        ->and($user->can('view', $question))->toBeTrue()
        ->and($user->can('update', $question))->toBeTrue()
        ->and($user->can('delete', $question))->toBeTrue();
});

test('een gemeentelijk beheerder komt niet bij de vragen van een andere gemeente', function () {
    $own = Municipality::factory()->create();
    $other = Municipality::factory()->create();

    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($own);

    $question = MunicipalityFormQuestion::factory()->create(['municipality_id' => $other->id]);

    expect($user->can('view', $question))->toBeFalse()
        ->and($user->can('update', $question))->toBeFalse()
        ->and($user->can('delete', $question))->toBeFalse();
});

test('een organisator komt er helemaal niet bij', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $question = MunicipalityFormQuestion::factory()->create();

    expect($organiser->can('viewAny', MunicipalityFormQuestion::class))->toBeFalse()
        ->and($organiser->can('create', MunicipalityFormQuestion::class))->toBeFalse()
        ->and($organiser->can('view', $question))->toBeFalse();
});

test('herstellen en definitief verwijderen blijven dicht', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $question = MunicipalityFormQuestion::factory()->create();

    expect($admin->can('restore', $question))->toBeFalse()
        ->and($admin->can('forceDelete', $question))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Beheer-UI in het gemeentepaneel
// ---------------------------------------------------------------------------

test('de tabel toont de verwachte kolommen', function () {
    $municipality = Municipality::factory()->create();
    actAsMunicipalityAdmin($municipality);

    livewire(ListMunicipalityFormQuestions::class)
        ->assertTableColumnExists('order')
        ->assertTableColumnExists('label')
        ->assertTableColumnExists('type')
        ->assertTableColumnExists('is_required')
        ->assertTableColumnExists('is_active');
});

test('de tabel toont de vragen op volgorde', function () {
    $municipality = Municipality::factory()->create();

    $third = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 3]);
    $first = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1]);
    $second = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 2]);

    actAsMunicipalityAdmin($municipality);

    livewire(ListMunicipalityFormQuestions::class)
        ->assertCanSeeTableRecords([$first, $second, $third], inOrder: true);
});

test('de kolom "Tonen bij" toont de labels, en "Alle aanvragen" bij geen selectie', function () {
    $municipality = Municipality::factory()->create();

    $overal = MunicipalityFormQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 1,
    ]);
    $alleenMelding = MunicipalityFormQuestion::factory()
        ->forAanvraagTypes([DetermineAanvraagType::MELDING])
        ->create(['municipality_id' => $municipality->id, 'order' => 2]);

    actAsMunicipalityAdmin($municipality);

    livewire(ListMunicipalityFormQuestions::class)
        ->assertTableColumnExists('show_for_aanvraag_types')
        ->assertTableColumnStateSet('show_for_aanvraag_types', ['Alle aanvragen'], $overal)
        ->assertTableColumnStateSet('show_for_aanvraag_types', ['Melding'], $alleenMelding);
});

test('filteren op aanvraagtype toont ook de vragen die voor alle paden gelden', function () {
    // Een vraag zonder padselectie geldt voor ieder pad, dus die hoort bij
    // elk gefilterd pad zichtbaar te blijven.
    $municipality = Municipality::factory()->create();

    $overal = MunicipalityFormQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 1,
    ]);
    $alleenMelding = MunicipalityFormQuestion::factory()
        ->forAanvraagTypes([DetermineAanvraagType::MELDING])
        ->create(['municipality_id' => $municipality->id, 'order' => 2]);
    $alleenVergunning = MunicipalityFormQuestion::factory()
        ->forAanvraagTypes([DetermineAanvraagType::VERGUNNING])
        ->create(['municipality_id' => $municipality->id, 'order' => 3]);

    actAsMunicipalityAdmin($municipality);

    livewire(ListMunicipalityFormQuestions::class)
        ->filterTable('show_for_aanvraag_types', DetermineAanvraagType::MELDING)
        ->assertCanSeeTableRecords([$overal, $alleenMelding])
        ->assertCanNotSeeTableRecords([$alleenVergunning]);
});

test('zonder filterkeuze blijven alle vragen zichtbaar', function () {
    $municipality = Municipality::factory()->create();

    $overal = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1]);
    $alleenVergunning = MunicipalityFormQuestion::factory()
        ->forAanvraagTypes([DetermineAanvraagType::VERGUNNING])
        ->create(['municipality_id' => $municipality->id, 'order' => 2]);

    actAsMunicipalityAdmin($municipality);

    livewire(ListMunicipalityFormQuestions::class)
        ->filterTable('show_for_aanvraag_types', null)
        ->assertCanSeeTableRecords([$overal, $alleenVergunning]);
});

test('een gemeentelijk beheerder kan een keuzevraag aanmaken', function () {
    $municipality = Municipality::factory()->create();
    actAsMunicipalityAdmin($municipality);

    livewire(CreateMunicipalityFormQuestion::class)
        ->fillForm([
            'type' => MunicipalityFormQuestionType::Radio->value,
            'label' => 'Komt er een tent?',
            'options' => ['Ja', 'Nee'],
            'is_required' => true,
            'is_active' => true,
            'show_for_aanvraag_types' => [DetermineAanvraagType::VERGUNNING],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = $municipality->formQuestions()->firstOrFail();

    expect($question->type)->toBe(MunicipalityFormQuestionType::Radio)
        ->and($question->label)->toBe('Komt er een tent?')
        ->and($question->options)->toBe(['Ja', 'Nee'])
        ->and($question->is_required)->toBeTrue()
        ->and($question->order)->toBe(1)
        ->and($question->show_for_aanvraag_types)->toBe([DetermineAanvraagType::VERGUNNING]);
});

test('een tekstblok kan aangemaakt worden zonder opties', function () {
    // De optielijst is verborgen bij dit type; de min-2-regel mag hier dus
    // niet meevalideren.
    $municipality = Municipality::factory()->create();
    actAsMunicipalityAdmin($municipality);

    livewire(CreateMunicipalityFormQuestion::class)
        ->fillForm([
            'type' => MunicipalityFormQuestionType::Text->value,
            'label' => 'Wilt u nog iets kwijt?',
            'helper_text' => 'Optioneel.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = $municipality->formQuestions()->firstOrFail();

    expect($question->type)->toBe(MunicipalityFormQuestionType::Text)
        ->and($question->options)->toBeNull()
        ->and($question->helper_text)->toBe('Optioneel.')
        ->and($question->is_required)->toBeFalse()
        ->and($question->show_for_aanvraag_types)->toBeNull();
});

test('een keuzevraag met minder dan twee opties wordt geweigerd', function () {
    $municipality = Municipality::factory()->create();
    actAsMunicipalityAdmin($municipality);

    livewire(CreateMunicipalityFormQuestion::class)
        ->fillForm([
            'type' => MunicipalityFormQuestionType::Checkboxes->value,
            'label' => 'Wat past?',
            'options' => ['Alleen deze'],
        ])
        ->call('create')
        ->assertHasFormErrors(['options']);

    expect($municipality->formQuestions()->count())->toBe(0);
});

test('een vraag zonder tekst wordt geweigerd', function () {
    $municipality = Municipality::factory()->create();
    actAsMunicipalityAdmin($municipality);

    livewire(CreateMunicipalityFormQuestion::class)
        ->fillForm(['type' => MunicipalityFormQuestionType::Text->value, 'label' => ''])
        ->call('create')
        ->assertHasFormErrors(['label' => 'required']);
});

test('een vraag kan bewerkt en gedeactiveerd worden', function () {
    $municipality = Municipality::factory()->create();
    $question = MunicipalityFormQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'label' => 'Oude tekst',
    ]);

    actAsMunicipalityAdmin($municipality);

    livewire(EditMunicipalityFormQuestion::class, ['record' => $question->id])
        ->fillForm(['label' => 'Nieuwe tekst', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($question->fresh()->label)->toBe('Nieuwe tekst')
        ->and($question->fresh()->is_active)->toBeFalse();
});

test('een vraag kan verwijderd worden', function () {
    $municipality = Municipality::factory()->create();
    $question = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id]);

    actAsMunicipalityAdmin($municipality);

    livewire(EditMunicipalityFormQuestion::class, ['record' => $question->id])
        ->callAction('delete');

    expect(MunicipalityFormQuestion::whereKey($question->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Herordenen
// ---------------------------------------------------------------------------

test('herordenen met vijftien vragen loopt niet op de unique-constraint stuk', function () {
    $municipality = Municipality::factory()->create();

    $questions = collect(range(1, 15))->map(fn (int $i) => MunicipalityFormQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => $i,
        'label' => "Vraag $i",
    ]));

    actAsMunicipalityAdmin($municipality);

    $reversed = $questions->reverse()->pluck('id')->all();

    livewire(ListMunicipalityFormQuestions::class)->call('reorderTable', $reversed);

    foreach ($questions->reverse()->values() as $newPosition => $question) {
        expect($question->fresh()->order)->toBe($newPosition + 1);
    }
});

test('herordenen raakt de vragen van een andere gemeente niet', function () {
    $municipality = Municipality::factory()->create();
    $other = Municipality::factory()->create();

    $first = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1]);
    $second = MunicipalityFormQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 2]);
    $foreign = MunicipalityFormQuestion::factory()->create(['municipality_id' => $other->id, 'order' => 1]);

    actAsMunicipalityAdmin($municipality);

    livewire(ListMunicipalityFormQuestions::class)->call('reorderTable', [$second->id, $first->id]);

    expect($second->fresh()->order)->toBe(1)
        ->and($first->fresh()->order)->toBe(2)
        ->and($foreign->fresh()->order)->toBe(1);
});

test('herordenen via de admin-relation-manager werkt ook', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $municipality = Municipality::factory()->create();

    $questions = collect(range(1, 5))->map(fn (int $i) => MunicipalityFormQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => $i,
        'label' => "Vraag $i",
    ]));

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(MunicipalityFormQuestionsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])->call('reorderTable', $questions->reverse()->pluck('id')->all());

    foreach ($questions->reverse()->values() as $newPosition => $question) {
        expect($question->fresh()->order)->toBe($newPosition + 1);
    }
});

// ---------------------------------------------------------------------------
// Doorgifte naar de FormState
// ---------------------------------------------------------------------------

test('de service levert alleen actieve vragen, op volgorde', function () {
    $municipality = Municipality::factory()->create();

    MunicipalityFormQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 2,
        'label' => 'Tweede',
    ]);
    MunicipalityFormQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 1,
        'label' => 'Eerste',
    ]);
    MunicipalityFormQuestion::factory()->inactive()->create([
        'municipality_id' => $municipality->id,
        'order' => 3,
        'label' => 'Inactief',
    ]);

    $map = app(MunicipalityVariablesService::class)->forMunicipalityAsKeyValue($municipality);

    expect(array_column($map['extra_questions'], 'label'))->toBe(['Eerste', 'Tweede']);
});

test('de service levert per vraag de volledige vorm die het formulier verwacht', function () {
    $municipality = Municipality::factory()->create();

    $question = MunicipalityFormQuestion::factory()
        ->radio(['Ja', 'Nee'])
        ->required()
        ->forAanvraagTypes([DetermineAanvraagType::MELDING])
        ->create([
            'municipality_id' => $municipality->id,
            'order' => 1,
            'label' => 'Komt er muziek?',
            'helper_text' => 'Ook achtergrondmuziek telt.',
        ]);

    $map = app(MunicipalityVariablesService::class)->forMunicipalityAsKeyValue($municipality);

    expect($map['extra_questions'])->toBe([[
        'id' => $question->id,
        'order' => 1,
        'type' => 'radio',
        'label' => 'Komt er muziek?',
        'helper_text' => 'Ook achtergrondmuziek telt.',
        'options' => ['Ja', 'Nee'],
        'is_required' => true,
        'show_for_aanvraag_types' => [DetermineAanvraagType::MELDING],
    ]]);
});

test('een gemeente zonder vragen levert een lege lijst', function () {
    $municipality = Municipality::factory()->create();

    $map = app(MunicipalityVariablesService::class)->forMunicipalityAsKeyValue($municipality);

    expect($map['extra_questions'])->toBe([]);
});
