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
use Filament\Forms\Components\Select;
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

/**
 * The options of the "who may see this document" select in the multi upload
 * schema, or null when the schema does not offer the select at all.
 *
 * @return array<string, string>|null
 */
function confidentialityOptions(Zaak $zaak): ?array
{
    foreach (UploadDocumentAction::schema($zaak) as $field) {
        if ($field instanceof Select && $field->getName() === 'vertrouwelijkheidaanduiding') {
            return $field->getOptions();
        }
    }

    return null;
}

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

test('the confidentiality choices are labelled with the enum defaults on a connection without a map', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->coordinator);

    expect(confidentialityOptions($zaak))->toBe([
        DocumentVertrouwelijkheden::Zaakvertrouwelijk->value => 'Gemeente, Adviseur, Organisator',
        DocumentVertrouwelijkheden::Vertrouwelijk->value => 'Gemeente, Adviseur',
        DocumentVertrouwelijkheden::Confidentieel->value => 'Gemeente',
    ]);
});

test('the confidentiality choices are labelled with the audiences of the active connection', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    // This connection lets the advisor see zaakvertrouwelijk documents, which
    // the hardcoded defaults do not, and keeps the organiser on openbaar only.
    Config::set('zgw.connections.main.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar'],
        Role::Advisor->value => ['openbaar', 'zaakvertrouwelijk'],
        Role::Reviewer->value => ['openbaar', 'zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'],
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->coordinator);

    expect(confidentialityOptions($zaak))->toBe([
        DocumentVertrouwelijkheden::Zaakvertrouwelijk->value => 'Gemeente, Adviseur',
        DocumentVertrouwelijkheden::Vertrouwelijk->value => 'Gemeente',
        DocumentVertrouwelijkheden::Confidentieel->value => 'Gemeente',
    ]);
});

test('the confidentiality select disappears when the levels reach the same audience', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    // The situation reported on the VRZL staging connection: the municipal roles
    // see every level and nobody else sees any of them, so the three choices
    // make no difference at all.
    Config::set('zgw.connections.main.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar'],
        Role::Advisor->value => ['openbaar'],
        Role::Reviewer->value => ['openbaar', 'zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'],
    ]);

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

    expect($fieldNames)->not->toContain('vertrouwelijkheidaanduiding');
});

test('an upload without the select applies the upload default of the connection', function () {
    Queue::fake();
    Storage::fake('local');

    $path = 'documents/coordinator-file.pdf';
    Storage::put($path, '%PDF-1.4 coordinator file');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    Config::set('zgw.connections.main.vertrouwelijkheid_map', [
        'visibility' => [
            Role::Organiser->value => ['openbaar'],
            Role::Advisor->value => ['openbaar'],
            Role::Reviewer->value => ['openbaar', 'zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'],
            Role::Coordinator->value => ['openbaar', 'zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'],
        ],
        'upload_default' => [
            Role::Coordinator->value => DocumentVertrouwelijkheden::Confidentieel->value,
        ],
    ]);

    Http::fake([
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
