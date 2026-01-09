<?php

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Advisor\Resources\Zaken\ZaakResource\Pages\ListAllZaken;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    // Advisory without can_view_any_zaak
    $this->advisoryWithoutAccess = Advisory::factory()->create([
        'name' => 'Limited Advisory',
        'can_view_any_zaak' => false,
    ]);

    // Advisory with can_view_any_zaak
    $this->advisoryWithAccess = Advisory::factory()->create([
        'name' => 'Full Access Advisory',
        'can_view_any_zaak' => true,
    ]);

    $this->advisorWithoutAccess = User::factory()->create([
        'email' => 'limited@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisorWithAccess = User::factory()->create([
        'email' => 'full@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisoryWithoutAccess->users()->attach($this->advisorWithoutAccess, ['role' => AdvisoryRole::Member]);
    $this->advisoryWithAccess->users()->attach($this->advisorWithAccess, ['role' => AdvisoryRole::Member]);

    // Create zaak with advice thread for limited advisory
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();

    $this->zaakWithAdvice = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => new ZaakReferenceData(
            'A',
            now(),
            now()->addDay(),
            now(),
            'Ontvangen',
            'Test locatie',
            'Test event'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $this->zaakWithAdvice->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisoryWithoutAccess->id,
        'advice_status' => \App\Enums\AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Test Advice',
    ]);

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('2');

    // Create zaak without advice thread
    $this->zaakWithoutAdvice = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => new ZaakReferenceData(
            'B',
            now(),
            now()->addDay(),
            now(),
            'Ontvangen',
            'Another locatie',
            'Another event'
        ),
    ]);

    ZgwHttpFake::wildcardFake();
});

// test('advisory without can_view_any_zaak cannot view zaak without advice thread', function () {
//    Filament::setCurrentPanel(Filament::getPanel('advisor'));
//    $this->actingAs($this->advisorWithoutAccess);
//    Filament::setTenant($this->advisoryWithoutAccess);
//
//    livewire(ViewZaak::class, [
//        'record' => $this->zaakWithoutAdvice->id,
//    ])->assertNotFound();
// });

test('advisory without can_view_any_zaak can view zaak with advice thread', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithoutAccess);
    Filament::setTenant($this->advisoryWithoutAccess);

    livewire(ViewZaak::class, [
        'record' => $this->zaakWithAdvice->id,
    ])
        ->assertSuccessful();
});

test('advisory with can_view_any_zaak can view zaak without advice thread', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithAccess);
    Filament::setTenant($this->advisoryWithAccess);

    livewire(ViewZaak::class, [
        'record' => $this->zaakWithoutAdvice->id,
    ])
        ->assertSuccessful();
});

test('advisory with can_view_any_zaak can view all zaken page', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithAccess);
    Filament::setTenant($this->advisoryWithAccess);

    livewire(ListAllZaken::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$this->zaakWithAdvice, $this->zaakWithoutAdvice]);
});

test('advisory without can_view_any_zaak can not view all zaken page', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithoutAccess);
    Filament::setTenant($this->advisoryWithoutAccess);

    livewire(ListAllZaken::class)
        ->assertForbidden();
});

test('advisory without can_view_any_zaak cannot upload documents to zaak without advice thread', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithoutAccess);
    Filament::setTenant($this->advisoryWithoutAccess);

    $canUpload = $this->advisorWithoutAccess->can('uploadDocument', $this->zaakWithoutAdvice);

    expect($canUpload)->toBeFalse();
});

test('advisory with can_view_any_zaak cannot upload documents to zaak without advice thread', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithAccess);
    Filament::setTenant($this->advisoryWithAccess);

    $canUpload = $this->advisorWithAccess->can('uploadDocument', $this->zaakWithoutAdvice);

    expect($canUpload)->toBeFalse();
});

test('advisory without can_view_any_zaak can upload documents to zaak with advice thread', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithoutAccess);
    Filament::setTenant($this->advisoryWithoutAccess);

    $canUpload = $this->advisorWithoutAccess->can('uploadDocument', $this->zaakWithAdvice);

    expect($canUpload)->toBeTrue();
});

test('advisory with can_view_any_zaak can upload documents only to zaak with advice thread', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithAccess);
    Filament::setTenant($this->advisoryWithAccess);

    // Create zaak with advice thread for advisory with access
    $zaakWithAdviceForFullAccess = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            'C',
            now(),
            now()->addDay(),
            now(),
            'Ontvangen',
            'Third locatie',
            'Third event'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $zaakWithAdviceForFullAccess->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisoryWithAccess->id,
        'advice_status' => \App\Enums\AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Test Advice for Full Access',
    ]);

    $canUploadWithAdvice = $this->advisorWithAccess->can('uploadDocument', $zaakWithAdviceForFullAccess);
    $canUploadWithoutAdvice = $this->advisorWithAccess->can('uploadDocument', $this->zaakWithoutAdvice);

    expect($canUploadWithAdvice)->toBeTrue()
        ->and($canUploadWithoutAdvice)->toBeFalse();
});

test('advisory with can_view_any_zaak has readonly access to zaak without advice thread', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->advisorWithAccess);
    Filament::setTenant($this->advisoryWithAccess);

    // Can view
    $canView = $this->advisorWithAccess->can('view', $this->zaakWithoutAdvice);

    // Cannot update
    $canUpdate = $this->advisorWithAccess->can('update', $this->zaakWithoutAdvice);

    // Cannot delete
    $canDelete = $this->advisorWithAccess->can('delete', $this->zaakWithoutAdvice);

    // Cannot upload documents
    $canUpload = $this->advisorWithAccess->can('uploadDocument', $this->zaakWithoutAdvice);

    expect($canView)->toBeTrue()
        ->and($canUpdate)->toBeFalse()
        ->and($canDelete)->toBeFalse()
        ->and($canUpload)->toBeFalse();
});
