<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use Livewire\Livewire;
use App\Filament\Organiser\Pages\EventFormPage;

$user = User::factory()->create(['role' => Role::Organiser]);
$org = Organisation::factory()->create();
$user->organisations()->attach($org->id, ['role' => 'admin']);
auth()->login($user);
\Filament\Facades\Filament::setTenant($org);

$test = Livewire::test(EventFormPage::class);
$html = $test->html();

// Check of wire:model voor velden op alle stappen in de HTML staat
$needles = [
    'watIsUwVoornaam' => 'stap 1',
    'watIsDeNaamVanHetEvenementVergunning' => 'stap 2',
    'waarVindtHetEvenementPlaats' => 'stap 3',
    'EvenementStart' => 'stap 4',
    'hoeWiltUPromotieMakenVoorUwEvenement' => 'stap ?',
    'isUwEvenementToegankelijkVoorMensenMetEenBeperking' => 'stap ?',
    'geefEenOmschrijvingVanSoortOmheining' => 'stap ?',
];

foreach ($needles as $k => $where) {
    $found = str_contains($html, 'wire:model="data.'.$k.'"') || str_contains($html, 'wire:model.defer="data.'.$k.'"');
    echo ($found ? '✅' : '❌')." {$k} ({$where})\n";
}
echo "\nHTML size: ".strlen($html)." chars\n";
