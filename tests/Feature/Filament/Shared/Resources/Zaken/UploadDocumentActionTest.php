<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->organiser = User::factory()->create([
        'role' => Role::Organiser,
    ]);

    $this->organisation = Organisation::factory()->create([
        'type' => 'business',
    ]);

    $this->organisation->users()->attach($this->organiser, [
        'role' => OrganisationRole::Admin,
    ]);

    $this->zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
});

test('upload action exists on ZaakDocumentsTable for authorised organiser', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertOk()
        ->assertTableActionExists('upload');
});

test('upload action is visible for organiser belonging to the zaak organisation', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertTableActionVisible('upload');
});

test('upload action is hidden for organiser not belonging to the zaak organisation', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $unauthorisedOrganiser = User::factory()->create(['role' => Role::Organiser]);
    $otherOrganisation = Organisation::factory()->create(['type' => 'business']);
    $otherOrganisation->users()->attach($unauthorisedOrganiser, ['role' => OrganisationRole::Admin]);

    $this->actingAs($unauthorisedOrganiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertTableActionHidden('upload');
});

test('upload action successfully stores a document via OpenZaak and dispatches refreshTable', function () {
    Storage::fake('local');

    $filePath = 'documents/test-document.pdf';
    $fileContents = '%PDF-1.4 fake pdf content for testing';
    Storage::put($filePath, $fileContents);

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();

    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';
    $documentUrl = ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/new-doc-1';

    Http::fake([
        // POST: store the informatieobject
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::response([
            'url' => $documentUrl,
            'uuid' => 'new-doc-1',
            'identificatie' => 'DOC-001',
            'titel' => 'Testbestand',
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            'auteur' => $this->organiser->name,
            'versie' => 1,
            'bestandsnaam' => 'test-document.pdf',
            'inhoud' => base64_encode($fileContents),
            'beschrijving' => '',
            'formaat' => 'application/pdf',
            'locked' => false,
            'bestandsgrootte' => strlen($fileContents),
            'creatiedatum' => now()->format('Y-m-d'),
            'wijzigingsdatum' => now()->toIso8601String(),
            'informatieobjecttype' => $documentTypeUrl,
            'indicatieGebruiksrecht' => false,
        ], 201),
        // POST: link the document to the zaak
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
            'zaak' => $zgwZaakUrl,
            'informatieobject' => $documentUrl,
        ], 201),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->organiser);

    $result = UploadDocumentAction::uploadDocument([
        'titel' => 'Testbestand',
        'informatieobjecttype' => $documentTypeUrl,
        'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        'file' => $filePath,
        'file_name' => 'test-document.pdf',
    ], $zaak);

    // Assert the POST to create the informatieobject was made
    Http::assertSent(fn ($request) => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['titel'] === 'Testbestand'
    );

    // Assert the POST to link the document to the zaak was made
    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaakinformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['zaak'] === $zgwZaakUrl
    );

    // Assert the returned value object has the expected URL
    expect($result->url)->toBe($documentUrl);

    // Assert the temporary file was deleted from storage after upload
    expect(Storage::exists($filePath))->toBeFalse();
});

test('upload action clears the documenten cache after storing a document', function () {
    Storage::fake('local');

    $filePath = 'documents/test-document.pdf';
    Storage::put($filePath, '%PDF-1.4 fake pdf content');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';
    $documentUrl = ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/new-doc-2';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::response([
            'url' => $documentUrl,
            'uuid' => 'new-doc-2',
            'identificatie' => 'DOC-002',
            'titel' => 'Cachetest bestand',
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            'auteur' => $this->organiser->name,
            'versie' => 1,
            'bestandsnaam' => 'test-document.pdf',
            'inhoud' => '',
            'beschrijving' => '',
            'formaat' => 'application/pdf',
            'locked' => false,
            'bestandsgrootte' => 0,
            'creatiedatum' => now()->format('Y-m-d'),
            'wijzigingsdatum' => now()->toIso8601String(),
            'informatieobjecttype' => $documentTypeUrl,
            'indicatieGebruiksrecht' => false,
        ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/2',
            'zaak' => $zgwZaakUrl,
            'informatieobject' => $documentUrl,
        ], 201),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    // Pre-warm the documenten cache to verify it gets busted by uploadDocument()
    Cache::put("zaak.{$zaak->id}.documenten", collect());
    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeTrue();

    $this->actingAs($this->organiser);

    UploadDocumentAction::uploadDocument([
        'titel' => 'Cachetest bestand',
        'informatieobjecttype' => $documentTypeUrl,
        'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        'file' => $filePath,
        'file_name' => 'test-document.pdf',
    ], $zaak);

    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeFalse();
});
