<?php

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->zaaktype1 = Zaaktype::factory()->create([
        'name' => 'Original Zaaktype',
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        'is_active' => true,
    ]);

    $this->zaaktype2 = Zaaktype::factory()->create([
        'name' => 'New Zaaktype',
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/2',
        'is_active' => true,
    ]);
});

test('admin can see change zaaktype action', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('test-uuid-1');
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype1->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, [
        'record' => $zaak->id,
    ])
        ->assertOk()
        ->assertActionVisible('change_zaaktype');
});

test('non-admin cannot see change zaaktype action', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('test-uuid-1');
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype1->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $reviewer = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::Reviewer,
    ]);

    $this->actingAs($reviewer);

    livewire(ViewZaak::class, [
        'record' => $zaak->id,
    ])
        ->assertOk()
        ->assertActionHidden('change_zaaktype');
});

test('admin can change zaaktype successfully', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('test-uuid-1', [
        'zaaktype' => $this->zaaktype1->zgw_zaaktype_url,
    ]);
    ZgwHttpFake::wildcardFake();

    // Mock the PATCH request to OpenZaak
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/test-uuid-1' => Http::response([
            'url' => $zgwZaakUrl,
            'uuid' => 'test-uuid-1',
            'zaaktype' => $this->zaaktype2->zgw_zaaktype_url,
        ], 200),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype1->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    // Set cache to ensure it gets cleared
    Cache::forever("zaak.{$zaak->id}.openzaak", 'cached_data');
    Cache::forever("zaak.{$zaak->id}.documenten", 'cached_data');

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, [
        'record' => $zaak->id,
    ])
        ->callAction('change_zaaktype', [
            'new_zaaktype_id' => $this->zaaktype2->id,
        ])
        ->assertHasNoActionErrors();

    // Assert zaaktype was updated in database
    $zaak->refresh();
    expect($zaak->zaaktype_id)->toBe($this->zaaktype2->id);

    // Assert cache was cleared
    expect(Cache::has("zaak.{$zaak->id}.openzaak"))->toBeFalse();
    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeFalse();

    // Assert OpenZaak API was called with correct data
    Http::assertSent(function ($request) {
        return $request->url() === ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/test-uuid-1'
            && $request->method() === 'PATCH'
            && $request['zaaktype'] === $this->zaaktype2->zgw_zaaktype_url;
    });
});

test('change zaaktype only shows zaaktypes from same municipality', function () {
    $otherMunicipality = Municipality::factory()->create([
        'name' => 'Other Municipality',
    ]);

    $otherZaaktype = Zaaktype::factory()->create([
        'name' => 'Other Municipality Zaaktype',
        'municipality_id' => $otherMunicipality->id,
        'is_active' => true,
    ]);

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('test-uuid-1');
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype1->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, [
        'record' => $zaak->id,
    ])
        ->mountAction('change_zaaktype')
        ->assertActionDataSet([])
        ->setActionData([
            'new_zaaktype_id' => $this->zaaktype2->id,
        ])
        ->assertHasNoActionErrors()
        ->setActionData([
            'new_zaaktype_id' => $otherZaaktype->id,
        ])
        ->callMountedAction()
        ->assertHasActionErrors(['new_zaaktype_id']);
});

test('change zaaktype excludes current zaaktype from options', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('test-uuid-1');
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype1->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->admin);

    $component = livewire(ViewZaak::class, [
        'record' => $zaak->id,
    ])
        ->mountAction('change_zaaktype');

    // Get the select options
    $action = $component->instance()->getCachedAction('change_zaaktype');
    $schema = $action->getFormSchema();
    $selectField = collect($schema)->first(fn ($field) => $field->getName() === 'new_zaaktype_id');
    $options = $selectField->getOptions();

    // Assert current zaaktype is not in options
    expect($options)->not->toHaveKey($this->zaaktype1->id);
    // Assert other zaaktype from same municipality is in options
    expect($options)->toHaveKey($this->zaaktype2->id);
});

test('change zaaktype only shows active zaaktypes', function () {
    $inactiveZaaktype = Zaaktype::factory()->create([
        'name' => 'Inactive Zaaktype',
        'municipality_id' => $this->municipality->id,
        'is_active' => false,
    ]);

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('test-uuid-1');
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype1->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->admin);

    $component = livewire(ViewZaak::class, [
        'record' => $zaak->id,
    ])
        ->mountAction('change_zaaktype');

    $action = $component->instance()->getCachedAction('change_zaaktype');
    $schema = $action->getFormSchema();
    $selectField = collect($schema)->first(fn ($field) => $field->getName() === 'new_zaaktype_id');
    $options = $selectField->getOptions();

    // Assert inactive zaaktype is not in options
    expect($options)->not->toHaveKey($inactiveZaaktype->id);
});

test('change zaaktype handles openzaak errors gracefully', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('test-uuid-1');
    ZgwHttpFake::wildcardFake();

    // Mock the PATCH request to fail
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/test-uuid-1' => Http::response([
            'detail' => 'OpenZaak error',
        ], 500),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype1->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $originalZaaktypeId = $zaak->zaaktype_id;

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, [
        'record' => $zaak->id,
    ])
        ->callAction('change_zaaktype', [
            'new_zaaktype_id' => $this->zaaktype2->id,
        ])
        ->assertNotified();

    // Assert zaaktype was NOT changed in database due to error
    $zaak->refresh();
    expect($zaak->zaaktype_id)->toBe($originalZaaktypeId);
});
