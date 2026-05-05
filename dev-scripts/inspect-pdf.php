<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Jobs\Submit\GenerateSubmissionPdf;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

Bus::fake();
Notification::fake();

DB::beginTransaction();
try {
    $m = Municipality::factory()->create(['name' => 'Heerlen']);
    $zt = Zaaktype::factory()->create(['municipality_id' => $m->id]);
    $org = Organisation::factory()->create();
    $zaak = Zaak::factory()->create([
        'organisation_id' => $org->id,
        'zaaktype_id' => $zt->id,
        'public_id' => 'ZAAK-T-1',
        'form_state_snapshot' => [
            'values' => ['watIsUwVoornaam' => 'Eva', 'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan'],
            'fieldHidden' => [],
            'stepApplicable' => [],
            'system' => [],
        ],
    ]);
    (new GenerateSubmissionPdf($zaak))->handle();
    $bytes = Storage::disk('local')->get(sprintf('zaken/%s/submission-report.pdf', $zaak->id));
    echo 'PDF size: '.strlen($bytes)."\n";
    preg_match_all('/stream\s*\n(.*?)\nendstream/s', $bytes, $m1);
    echo 'Streams found (\\n): '.count($m1[1])."\n";
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $m2);
    echo 'Streams (CRLF tolerant): '.count($m2[1])."\n";
    foreach ($m2[1] as $i => $s) {
        $deflated = @gzuncompress($s);
        $inflated = @gzinflate($s);
        echo "  Stream $i: len=".strlen($s).', gzuncompress='.($deflated !== false ? 'OK '.strlen($deflated) : 'FAIL').', gzinflate='.($inflated !== false ? 'OK '.strlen($inflated) : 'FAIL')."\n";
        $decoded = $deflated ?: $inflated;
        if ($decoded && strpos($decoded, 'Contact') !== false) {
            echo "    !! contains Contact\n";
        }
        if ($decoded && $i < 3) {
            $sample = preg_replace('/[^\x20-\x7e\n]/', '·', substr($decoded, 0, 250));
            echo "    sample: $sample\n";
        }
    }
} finally {
    DB::rollBack();
}
