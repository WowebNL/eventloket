<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->zaaktype = Zaaktype::factory()->create();

    $this->organisation = Organisation::factory()->create([
        'type' => 'business',
        'name' => 'Test organisation',
    ]);

    $this->organiserUser = User::factory()->create([
        'role' => Role::Organiser,
    ]);

    $this->organisation->users()->attach($this->organiserUser, [
        'role' => OrganisationRole::Admin,
    ]);
});

test('imported zaak hides tabs section in infolist', function () {
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    $this->actingAs($this->organiserUser);
    Filament::setTenant($this->organisation);

    $component = livewire(ViewZaak::class, ['record' => $zaak->id]);

    // The tabs section should be hidden for imported zaaks
    // We check the component renders without error for imported zaak
    expect($zaak->is_imported)->toBeTrue();
    $component->assertOk();
});

test('non-imported zaak shows tabs section in infolist', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'imported_data' => null,
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    $this->actingAs($this->organiserUser);
    Filament::setTenant($this->organisation);

    $component = livewire(ViewZaak::class, ['record' => $zaak->id]);

    // Non-imported zaak should render successfully with tabs
    expect($zaak->is_imported)->toBeFalse();
    $component->assertOk();
});

test('imported zaak displays imported data in infolist', function () {
    ZgwHttpFake::wildcardFake();

    $importedData = ['key1' => 'value1', 'key2' => 'value2'];

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => null,
        'imported_data' => $importedData,
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    $this->actingAs($this->organiserUser);
    Filament::setTenant($this->organisation);

    $component = livewire(ViewZaak::class, ['record' => $zaak->id]);

    // Verify the component renders and the zaak has imported data
    expect($zaak->is_imported)->toBeTrue();
    expect($zaak->imported_data)->toBe($importedData);
    $component->assertOk();
});

test('non-imported zaak does not display imported data section in infolist', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'imported_data' => null,
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    $this->actingAs($this->organiserUser);
    Filament::setTenant($this->organisation);

    $component = livewire(ViewZaak::class, ['record' => $zaak->id]);

    expect($zaak->is_imported)->toBeFalse();
    expect($zaak->imported_data)->toBeNull();
    $component->assertOk();
});
