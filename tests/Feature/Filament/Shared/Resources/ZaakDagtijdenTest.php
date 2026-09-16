<?php

declare(strict_types=1);

/**
 * Issue #24: the per-day times of a multi-day event must be visible to the
 * case handler on the case itself, not only in the submission PDF.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    ZgwHttpFake::wildcardFake();

    $municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($this->user, ['role' => OrganisationRole::Admin]);

    $this->actingAs($this->user);
    Filament::setTenant($this->organisation);
});

function zaakMetDagtijden(array $overrides = []): Zaak
{
    return Zaak::factory()->create([
        'zaaktype_id' => test()->zaaktype->id,
        'organisation_id' => test()->organisation->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
        'reference_data' => new ZaakReferenceData(...array_merge([
            'start_evenement' => '2026-07-04T16:00:00+02:00',
            'eind_evenement' => '2026-07-07T02:00:00+02:00',
            'registratiedatum' => '2026-06-01T10:00:00+02:00',
            'status_name' => 'Ingediend',
            'statustype_url' => 'https://example.com/statustype/1',
            'naam_evenement' => 'Driedaags festival',
        ], $overrides)),
    ]);
}

test('de dagtijden van een meerdaags evenement staan op de zaak', function () {
    $zaak = zaakMetDagtijden([
        'dagen_evenement' => [
            ['datum' => '2026-07-04', 'start' => '2026-07-04T16:00:00+02:00', 'eind' => '2026-07-05T02:00:00+02:00'],
            ['datum' => '2026-07-05', 'start' => '2026-07-05T16:00:00+02:00', 'eind' => '2026-07-06T02:00:00+02:00'],
        ],
    ]);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->assertSee('Evenement per dag')
        ->assertSee('zaterdag 4 juli 2026 · 16:00 – 02:00 (5 juli)');
});

test('een zaak zonder dagtijden toont het kopje niet', function () {
    $zaak = zaakMetDagtijden();

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->assertDontSee('Evenement per dag')
        ->assertDontSee('Opbouw per dag');
});
