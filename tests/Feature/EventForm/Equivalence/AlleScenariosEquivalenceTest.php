<?php

declare(strict_types=1);

/**
 * Verzamelt alle ScenarioProvider-classes in deze directory en draait ze
 * tegen de echte Livewire EventFormPage — zodat we testen wat de user
 * daadwerkelijk in z'n browser zou zien, niet alleen een losse FormState-
 * aanroep. Velden doen hun visibility via een combinatie van Filament's
 * `->visible(Get $get)`-closures en FormFieldVisibility/FormStepApplicability
 * (pure-functioneel afgeleid uit FormState).
 */

use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\Feature\EventForm\Equivalence\EquivalenceScenario;
use Tests\Feature\EventForm\Equivalence\Scenarios\ScenarioProvider;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);
    $this->actingAs($this->user);
    Filament::setTenant($this->organisation);
});

/**
 * @return array<string, array<int, array<string, mixed>>>
 */
function alleScenariosViaProviders(): array
{
    $dir = __DIR__.'/Scenarios';
    $all = [];
    foreach (glob($dir.'/*.php') ?: [] as $file) {
        $basename = basename($file, '.php');
        if ($basename === 'ScenarioProvider') {
            continue;
        }
        $fqcn = 'Tests\\Feature\\EventForm\\Equivalence\\Scenarios\\'.$basename;
        if (! class_exists($fqcn)) {
            continue;
        }
        $reflection = new ReflectionClass($fqcn);
        if (! $reflection->implementsInterface(ScenarioProvider::class) || $reflection->isAbstract()) {
            continue;
        }
        foreach ($fqcn::all() as $label => $entry) {
            $all[$basename.' — '.$label] = $entry;
        }
    }

    return $all;
}

test(
    'Equivalentie-scenario volgt OF-gedrag op de echte Livewire-page: {0.naam}',
    function (array $scenario) {
        $diffs = EquivalenceScenario::runViaLivewire($scenario);

        expect($diffs)->toBe(
            [],
            sprintf(
                "Scenario faalt — %s\n\nOmschrijving: %s\n\nAfwijkingen: %s",
                $scenario['naam'],
                $scenario['omschrijving'],
                json_encode($diffs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ),
        );
    },
)->with(alleScenariosViaProviders());
