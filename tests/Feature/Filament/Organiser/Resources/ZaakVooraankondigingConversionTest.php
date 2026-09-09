<?php

declare(strict_types=1);

/**
 * The conversion of a vooraankondiging into the definitive aanvraag for a
 * municipality that koppelt the role to a zaaktype of its own ZGW instance.
 * The zaak page must offer "Definitieve aanvraag indienen" instead of the
 * generic repeat action, and the form must offer the vooraankondiging to
 * link, both of which used to depend on the zaaktype name.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin]);

    $this->municipality = Municipality::factory()->create();

    // The koppeling of the municipality: the vooraankondiging role points at a
    // zaaktype of its own instance, whose omschrijving carries no Eventloket
    // naming convention. The local row is what the sync leaves behind.
    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vooraankondiging,
        'zaaktype_identificatie' => 'EXT-1',
    ]);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'identificatie' => 'EXT-1',
        'name' => 'Activiteit behandelen',
        'role' => ZaaktypeRole::Vooraankondiging,
        'is_active' => true,
    ]);

    ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $this->vooraankondiging = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'organiser_user_id' => $this->organiser->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
        'form_state_snapshot' => [
            'values' => [
                'watIsDeNaamVanHetEvenementVergunning' => 'Testevenement',
                'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
            ],
            'system' => [],
        ],
    ]);

    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);
});

it('offers the conversion action on a koppeld vooraankondiging', function () {
    livewire(ViewZaak::class, ['record' => $this->vooraankondiging->id])
        ->assertOk()
        ->assertActionVisible('convert_vooraankondiging')
        ->assertActionHidden('prefil_new_request');
});

it('offers the generic repeat action on a zaak of another role', function () {
    $this->zaaktype->update(['role' => ZaaktypeRole::Melding]);
    MunicipalityZaaktypeMapping::query()->update(['role' => ZaaktypeRole::Melding->value]);

    livewire(ViewZaak::class, ['record' => $this->vooraankondiging->id])
        ->assertOk()
        ->assertActionHidden('convert_vooraankondiging')
        ->assertActionVisible('prefil_new_request');
});

it('offers the vooraankondiging question in the form for this organisation', function () {
    // The question and the select in Vragenboom2Step both hang on this check,
    // which runs the vooraankondigingen scope for the current tenant.
    $hasVooraankondiging = new ReflectionMethod(Vragenboom2Step::class, 'organisationHasVooraankondiging');

    expect($hasVooraankondiging->invoke(null))->toBeTrue();
});
