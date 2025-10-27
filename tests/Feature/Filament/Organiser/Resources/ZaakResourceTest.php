<?php

// use function Pest\Livewire\livewire;

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Organiser\Resources\Zaken\Pages\ListZaken;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->organiser = User::factory()->create([
        'email' => 'organiser@example.com',
        'role' => Role::Organiser,
    ]);

    $this->organisation = Organisation::factory()->create([
        'type' => 'business',
        'name' => 'Test organisation',
    ]);

    $this->organisation->users()->attach($this->organiser, [
        'role' => OrganisationRole::Admin,
    ]);

    $this->zaaktype = Zaaktype::factory()->create();
});

test('organisation user can see organisation requests list', function () {
    Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(ListZaken::class)
        ->assertCanSeeTableRecords([
            Zaak::first(),
        ]);
});

test('organisation user can only see requests from their organisation', function () {
    $otherOrganisation = Organisation::factory()->create([
        'type' => 'business',
        'name' => 'Other organisation',
    ]);

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();
    $ownZaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);
    $otherZaak = Zaak::factory()->create([
        'organisation_id' => $otherOrganisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(ViewZaak::class, [
        'record' => $otherZaak->id,
    ])->assertForbidden();

    livewire(ViewZaak::class, [
        'record' => $ownZaak->id,
    ])->assertOk();

});

test('organiser can withdraw a zaak without a result', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $zaaktypeurl = ZgwHttpFake::fakeSingleZaaktype();
    ZgwHttpFake::fakeResultaatTypen();
    ZgwHttpFake::wildcardFake();

    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeurl,
    ]);
    $ownZaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    Bus::fake();
    $this->actingAs($this->organiser);
    Filament::setTenant($this->organisation);

    livewire(ViewZaak::class, [
        'record' => $ownZaak->id,
    ])->assertOk()
        ->assertActionExists('withdraw')
        ->callAction('withdraw')
        ->assertNotified();

});
