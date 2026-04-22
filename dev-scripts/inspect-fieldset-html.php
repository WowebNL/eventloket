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

// Test 1: no-match (lege lijst)
$test = Livewire::test(EventFormPage::class);
$data = $test->instance()->data ?? [];
$data['extraContactpersonenToevoegen'] = [];
$test->set('data', $data);
$html = $test->html();

preg_match_all('#<legend[^>]*>(.*?)</legend>#is', $html, $matches);
echo "== NO-MATCH case — alle legends: ==\n";
foreach ($matches[1] as $c) {
    $clean = trim(preg_replace('/<!--.*?-->/s', '', $c));
    $plain = trim(strip_tags($clean));
    echo "  - ".substr($plain, 0, 80)."\n";
}

echo "\n== MATCH case — ['vooraf']: ==\n";
$test2 = Livewire::test(EventFormPage::class);
$data2 = $test2->instance()->data ?? [];
$data2['extraContactpersonenToevoegen'] = ['vooraf'];
$test2->set('data', $data2);
$html2 = $test2->html();
preg_match_all('#<legend[^>]*>(.*?)</legend>#is', $html2, $matches2);
foreach ($matches2[1] as $c) {
    $clean = trim(preg_replace('/<!--.*?-->/s', '', $c));
    $plain = trim(strip_tags($clean));
    echo "  - ".substr($plain, 0, 80)."\n";
}
