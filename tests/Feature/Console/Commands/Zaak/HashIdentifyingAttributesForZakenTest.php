<?php

use App\Jobs\Submit\HashIdentifyingAttributes;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function zaakForHashCatchUp(array $values, array $attributes = []): Zaak
{
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => Municipality::factory()->create()->id,
        'is_active' => true,
    ]);

    $zaak = Zaak::factory()->create(array_merge([
        'zaaktype_id' => $zaaktype->id,
        'form_state_snapshot' => ['values' => $values],
    ], $attributes));

    // created_at is not fillable; set it directly so the age filter can be exercised.
    $zaak->forceFill(['created_at' => now()->subHours(2)])->saveQuietly();

    return $zaak;
}

test('dispatches the hash job only for zaken that still hold a plain identifying value', function () {
    Bus::fake();

    $plainKvk = zaakForHashCatchUp(['watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678']);
    $plainBsn = zaakForHashCatchUp(['bsn' => '123456789']);
    zaakForHashCatchUp(['watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => HashIdentifyingAttributes::HASH_PREFIX.'abc']);
    zaakForHashCatchUp(['watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '']);
    zaakForHashCatchUp(['watIsDeNaamVanHetEvenementVergunning' => 'Voorbeeldloop']);

    $this->artisan('zaak:hash-identifying-attributes')->assertSuccessful();

    Bus::assertDispatchedTimes(HashIdentifyingAttributes::class, 2);
    Bus::assertDispatched(HashIdentifyingAttributes::class, fn ($job) => $job->zaak->is($plainKvk));
    Bus::assertDispatched(HashIdentifyingAttributes::class, fn ($job) => $job->zaak->is($plainBsn));
});

test('leaves recent zaken alone so a chain that is still running keeps its plain values', function () {
    Bus::fake();

    $recent = zaakForHashCatchUp(['watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678']);
    $recent->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();

    $this->artisan('zaak:hash-identifying-attributes')->assertSuccessful();

    Bus::assertNotDispatched(HashIdentifyingAttributes::class);
});

test('leaves deelzaken alone unless asked to include them', function () {
    Bus::fake();

    $hoofdzaak = zaakForHashCatchUp(['watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => HashIdentifyingAttributes::HASH_PREFIX.'abc']);
    $deelzaak = zaakForHashCatchUp(['watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678'], ['hoofdzaak_id' => $hoofdzaak->id]);

    $this->artisan('zaak:hash-identifying-attributes')->assertSuccessful();
    Bus::assertNotDispatched(HashIdentifyingAttributes::class);

    $this->artisan('zaak:hash-identifying-attributes', ['--include-deelzaken' => true])->assertSuccessful();
    Bus::assertDispatched(HashIdentifyingAttributes::class, fn ($job) => $job->zaak->is($deelzaak));
});

test('a dry run only reports', function () {
    Bus::fake();

    zaakForHashCatchUp(['watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678']);

    $this->artisan('zaak:hash-identifying-attributes', ['--dry-run' => true])
        ->expectsOutputToContain('1 zaak/zaken with a plain identifying value; nothing dispatched (dry run).')
        ->doesntExpectOutputToContain('12345678')
        ->assertSuccessful();

    Bus::assertNotDispatched(HashIdentifyingAttributes::class);
});

test('running it again after the hash jobs ran finds nothing left', function () {
    $zaak = zaakForHashCatchUp(['watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678']);

    // Sync queue: the dispatched job runs right away.
    $this->artisan('zaak:hash-identifying-attributes')->assertSuccessful();

    expect($zaak->fresh()->form_state_snapshot['values']['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'])
        ->toStartWith(HashIdentifyingAttributes::HASH_PREFIX);

    $this->artisan('zaak:hash-identifying-attributes', ['--dry-run' => true])
        ->expectsOutputToContain('0 zaak/zaken with a plain identifying value')
        ->assertSuccessful();
});
