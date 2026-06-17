<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Jobs\Zaak\UploadDocumentsJob;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
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
    $this->zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
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
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen*' => Http::response([
            [
                'uuid' => '1',
                'url' => $documentTypeUrl,
                'omschrijving' => 'Bijlage',
                'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
            ],
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
