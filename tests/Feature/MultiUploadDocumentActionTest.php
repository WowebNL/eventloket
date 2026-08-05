<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Jobs\Zaak\UploadDocumentsJob;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin]);
    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        'municipality_id' => $this->municipality->id,
    ]);

    $this->coordinator = User::factory()->create(['role' => Role::Coordinator]);
    $this->coordinator->municipalities()->attach($this->municipality);
});

test('upload action exists on ZaakDocumentsTable', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertTableActionExists('upload');
});

test('upload action dispatches UploadDocumentsJob with file data', function () {
    Queue::fake();
    Storage::fake('local');

    $paths = ['documents/file1.pdf', 'documents/file2.pdf'];
    Storage::put($paths[0], '%PDF-1.4 first file');
    Storage::put($paths[1], '%PDF-1.4 second file');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    Http::fake([
        // Document types are resolved via the zaaktype-informatieobjecttypen relation,
        // each row linking to one informatieobjecttype fetched by its url.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            [
                'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen/1',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'informatieobjecttype' => $documentTypeUrl,
            ],
        ]), 200),
        $documentTypeUrl => Http::response([
            'uuid' => '1',
            'url' => $documentTypeUrl,
            'omschrijving' => 'Bijlage',
            'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ], 200),
        ZgwHttpFake::$baseUrl.'*' => Http::response([], 200),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->callTableAction('upload', data: [
            'files' => $paths,
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'path' => $paths[0], 'titel' => 'Contract', 'informatieobjecttype' => $documentTypeUrl],
                ['_temp_path' => '/tmp/php2', 'path' => $paths[1], 'titel' => 'Bijlage', 'informatieobjecttype' => $documentTypeUrl],
            ],
        ])
        ->assertHasNoTableActionErrors();

    Queue::assertPushed(UploadDocumentsJob::class, function (UploadDocumentsJob $job) use ($zaak, $paths, $documentTypeUrl) {
        return $job->zaak->id === $zaak->id
            && count($job->files) === 2
            && $job->files[0]['path'] === $paths[0]
            && $job->files[0]['titel'] === 'Contract'
            && $job->files[0]['original_name'] === basename($paths[0]) // basename: storeFileNamesIn is not processed in test context
            && $job->files[0]['informatieobjecttype'] === $documentTypeUrl
            && $job->files[1]['path'] === $paths[1]
            && $job->files[1]['titel'] === 'Bijlage'
            && $job->files[1]['original_name'] === basename($paths[1])
            && $job->files[1]['informatieobjecttype'] === $documentTypeUrl;
    });
});

test('download documents bulk action exists on ZaakDocumentsTable', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertTableBulkActionExists('download-documents');
});

test('coordinator gets the confidentiality select in the multi upload schema', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->coordinator);

    $fieldNames = array_map(
        fn (Field|Repeater $field): string => $field->getName(),
        UploadDocumentAction::schema($zaak),
    );

    expect($fieldNames)->toContain('vertrouwelijkheidaanduiding');
});

test('organiser does not get the confidentiality select in the multi upload schema', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->organiser);

    $fieldNames = array_map(
        fn (Field|Repeater $field): string => $field->getName(),
        UploadDocumentAction::schema($zaak),
    );

    expect($fieldNames)->not->toContain('vertrouwelijkheidaanduiding');
});

test('coordinator can choose the confidentiality level when uploading documents', function () {
    Queue::fake();
    Storage::fake('local');

    $path = 'documents/coordinator-file.pdf';
    Storage::put($path, '%PDF-1.4 coordinator file');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    Http::fake([
        // Document types are resolved via the zaaktype-informatieobjecttypen relation,
        // each row linking to one informatieobjecttype fetched by its url.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            [
                'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen/1',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'informatieobjecttype' => $documentTypeUrl,
            ],
        ]), 200),
        $documentTypeUrl => Http::response([
            'uuid' => '1',
            'url' => $documentTypeUrl,
            'omschrijving' => 'Bijlage',
            'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ], 200),
        ZgwHttpFake::$baseUrl.'*' => Http::response([], 200),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->coordinator);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->callTableAction('upload', data: [
            'files' => [$path],
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Confidentieel->value,
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'path' => $path, 'titel' => 'Intern advies', 'informatieobjecttype' => $documentTypeUrl],
            ],
        ])
        ->assertHasNoTableActionErrors();

    Queue::assertPushed(
        UploadDocumentsJob::class,
        fn (UploadDocumentsJob $job): bool => $job->vertrouwelijkheidaanduiding === DocumentVertrouwelijkheden::Confidentieel->value,
    );
});
