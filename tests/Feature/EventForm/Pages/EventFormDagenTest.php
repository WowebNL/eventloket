<?php

declare(strict_types=1);

/**
 * Issue #24, in the real form: as soon as the given period covers more than
 * one calendar day, day rows appear in which the organiser fills in a start
 * and end time per day. A single-day event with an end time in the small
 * hours stays single-day and therefore gets no day rows.
 */

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
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

test('een meerdaagse periode levert een dagregel per evenementdag op', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    $component->set('data.EvenementStart', '2026-07-04 16:00:00');
    $component->set('data.EvenementEind', '2026-07-07 02:00:00');

    $dagen = $component->get('data.EvenementDagen');

    // The last block ends on 7 July at 02:00, but 7 July is not itself an
    // event day: that night belongs to 6 July.
    expect(array_keys($dagen))->toBe(['2026-07-04', '2026-07-05', '2026-07-06']);

    // The envelope supplies the first start time and the last end time.
    expect($dagen['2026-07-04']['startTijd'])->toBe('16:00');
    expect($dagen['2026-07-06']['eindTijd'])->toBe('02:00');
});

test('een eendaags evenement met een eindtijd in de nacht krijgt geen dagregels', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    $component->set('data.EvenementStart', '2026-07-04 16:00:00');
    $component->set('data.EvenementEind', '2026-07-05 02:00:00');

    expect($component->get('data.EvenementDagen'))->toBe([]);
});

test('een ingevulde dagtijd blijft staan als de periode langer wordt', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    $component->set('data.EvenementStart', '2026-07-04 16:00:00');
    $component->set('data.EvenementEind', '2026-07-06 23:00:00');
    $component->set('data.EvenementDagen.2026-07-05.startTijd', '14:00');
    $component->set('data.EvenementDagen.2026-07-05.eindTijd', '23:00');

    $component->set('data.EvenementEind', '2026-07-08 23:00:00');

    $dagen = $component->get('data.EvenementDagen');

    expect(array_keys($dagen))->toBe(['2026-07-04', '2026-07-05', '2026-07-06', '2026-07-07', '2026-07-08']);
    expect($dagen['2026-07-05']['startTijd'])->toBe('14:00');
    expect($dagen['2026-07-05']['eindTijd'])->toBe('23:00');
});

test('een bestaand concept met alleen een periode krijgt bij openen alsnog dagregels', function () {
    // Drafts from before this feature, and copies of older cases, do have a
    // start and end but no day rows yet.
    $draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => ['values' => [
            'EvenementStart' => '2026-07-04 16:00:00',
            'EvenementEind' => '2026-07-06 23:00:00',
        ], 'system' => []],
        'current_step_key' => null,
    ]);

    $dagen = Livewire::test(EventFormPage::class, ['draft' => $draft->id])
        ->get('data.EvenementDagen');

    expect(array_keys($dagen))->toBe(['2026-07-04', '2026-07-05', '2026-07-06']);
    expect($dagen['2026-07-04']['startTijd'])->toBe('16:00');
});

test('opbouw over meerdere dagen krijgt zijn eigen dagregels', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    $component->set('data.zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Ja');
    $component->set('data.OpbouwStart', '2026-07-01 08:00:00');
    $component->set('data.OpbouwEind', '2026-07-03 18:00:00');

    expect(array_keys($component->get('data.OpbouwDagen')))
        ->toBe(['2026-07-01', '2026-07-02', '2026-07-03']);
    // The event itself is not filled in yet and therefore has no day rows.
    expect($component->get('data.EvenementDagen'))->toBeEmpty();
});
