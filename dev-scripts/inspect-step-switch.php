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

// Test: zonder step-override
echo "== Default (geen step in query) ==\n";
$test1 = Livewire::test(EventFormPage::class);
$html1 = $test1->html();
echo '  wire:model="data.watIsUwVoornaam" (stap 1): '.(str_contains($html1, 'wire:model="data.watIsUwVoornaam"') ? 'YES' : 'no')."\n";
echo '  wire:model="data.soortEvenement" (stap 2): '.(str_contains($html1, 'wire:model="data.soortEvenement"') ? 'YES' : 'no')."\n";

// Met step-query voor stap 2
echo "\n== Met ?step=form.<stap2-uuid> ==\n";
request()->query->set('step', 'form.c3c17c65-0cf1-4a79-a348-75eab01f46ec');
$test2 = Livewire::test(EventFormPage::class);
$html2 = $test2->html();
echo '  wire:model="data.watIsUwVoornaam" (stap 1): '.(str_contains($html2, 'wire:model="data.watIsUwVoornaam"') ? 'YES' : 'no')."\n";
echo '  wire:model="data.soortEvenement" (stap 2): '.(str_contains($html2, 'wire:model="data.soortEvenement"') ? 'YES' : 'no')."\n";
