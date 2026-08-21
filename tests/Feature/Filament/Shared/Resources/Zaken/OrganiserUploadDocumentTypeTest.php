<?php

declare(strict_types=1);

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Jobs\Zaak\UploadDocumentsJob;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
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

/**
 * The zaaktype exposes two documenttypes the organiser may see. Their
 * omschrijvingen are chosen so the three possible outcomes are distinguishable:
 * "Aanvraagformulier" is the first type after sorting (the last-resort
 * heuristic), "Bijlage" is what the omschrijving heuristic prefers, and either
 * can be named by the koppeling.
 */
function fakeTwoDocumentTypes(): array
{
    $first = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';
    $second = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/2';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            [
                'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen/1',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'informatieobjecttype' => $first,
            ],
            [
                'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen/2',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'informatieobjecttype' => $second,
            ],
        ]), 200),
        $first => Http::response([
            'uuid' => '1',
            'url' => $first,
            'omschrijving' => 'Aanvraagformulier',
            'vertrouwelijkheidaanduiding' => 'openbaar',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ], 200),
        $second => Http::response([
            'uuid' => '2',
            'url' => $second,
            'omschrijving' => 'Bijlage',
            'vertrouwelijkheidaanduiding' => 'openbaar',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ], 200),
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/1',
            'uuid' => 'doc-1',
            'identificatie' => 'DOC-001',
            'titel' => 'Testbestand',
            'vertrouwelijkheidaanduiding' => 'openbaar',
            'auteur' => 'Tester',
            'versie' => 1,
            'bestandsnaam' => 'test-document.pdf',
            'inhoud' => '',
            'beschrijving' => '',
            'formaat' => 'application/pdf',
            'locked' => false,
            'bestandsgrootte' => 0,
            'creatiedatum' => now()->format('Y-m-d'),
            'wijzigingsdatum' => now()->toIso8601String(),
            'informatieobjecttype' => $first,
            'indicatieGebruiksrecht' => false,
        ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
        ], 201),
        ZgwHttpFake::$baseUrl.'*' => Http::response([], 200),
    ]);

    return ['aanvraagformulier' => $first, 'bijlage' => $second];
}

function mapBijlageDocumentType(Municipality $municipality, string $omschrijving): MunicipalityZaaktypeMapping
{
    return MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
        'bijlage_informatieobjecttype' => $omschrijving,
    ]);
}

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->municipality = Municipality::factory()->create();

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin]);

    $this->coordinator = User::factory()->create(['role' => Role::Coordinator]);
    $this->coordinator->municipalities()->attach($this->municipality);

    $this->zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        'municipality_id' => $this->municipality->id,
        'identificatie' => 'EVT-1',
    ]);

    // Registered before the per-test fakes so the more specific zaak stub is
    // matched ahead of their catch-all.
    $this->zaakUrl = ZgwHttpFake::fakeSingleZaak();

    $this->makeZaak = fn (): Zaak => Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $this->zaakUrl,
    ]);
});

test('organiser does not get a documenttype select in the multi upload schema', function () {
    fakeTwoDocumentTypes();
    $zaak = ($this->makeZaak)();

    $this->actingAs($this->organiser);

    $schema = UploadDocumentAction::schema($zaak);
    $fieldNames = array_map(fn (Field|Repeater $field): string => $field->getName(), $schema);

    expect($fieldNames)->not->toContain('bulk_informatieobjecttype');

    $repeater = collect($schema)->first(fn ($field): bool => $field->getName() === 'document_metadata');
    $repeaterFields = array_map(fn ($field): string => $field->getName(), $repeater->getDefaultChildComponents());

    expect($repeaterFields)->not->toContain('informatieobjecttype')
        ->and($repeaterFields)->toContain('titel');
});

test('coordinator still gets the documenttype selects in the multi upload schema', function () {
    fakeTwoDocumentTypes();
    $zaak = ($this->makeZaak)();

    $this->actingAs($this->coordinator);

    $schema = UploadDocumentAction::schema($zaak);
    $fieldNames = array_map(fn (Field|Repeater $field): string => $field->getName(), $schema);

    expect($fieldNames)->toContain('bulk_informatieobjecttype');

    $repeater = collect($schema)->first(fn ($field): bool => $field->getName() === 'document_metadata');
    $repeaterFields = array_map(fn ($field): string => $field->getName(), $repeater->getDefaultChildComponents());

    expect($repeaterFields)->toContain('informatieobjecttype');
});

test('organiser does not get a documenttype select in the single file schema', function () {
    fakeTwoDocumentTypes();
    $zaak = ($this->makeZaak)();

    $this->actingAs($this->organiser);

    $fieldNames = array_map(
        fn ($field): string => $field->getName(),
        UploadDocumentAction::singleFileSchema($zaak),
    );

    expect($fieldNames)->not->toContain('informatieobjecttype')
        ->and($fieldNames)->toContain('file');
});

test('coordinator still gets the documenttype select in the single file schema', function () {
    fakeTwoDocumentTypes();
    $zaak = ($this->makeZaak)();

    $this->actingAs($this->coordinator);

    $fieldNames = array_map(
        fn ($field): string => $field->getName(),
        UploadDocumentAction::singleFileSchema($zaak),
    );

    expect($fieldNames)->toContain('informatieobjecttype');
});

test('an organiser upload takes the documenttype from the koppeling, not from the submitted value', function () {
    Queue::fake();
    Storage::fake('local');

    $types = fakeTwoDocumentTypes();
    mapBijlageDocumentType($this->municipality, 'Aanvraagformulier');

    $path = 'documents/organiser-file.pdf';
    Storage::put($path, '%PDF-1.4 organiser file');

    $zaak = ($this->makeZaak)();

    $this->actingAs($this->organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->callTableAction('upload', data: [
            'files' => [$path],
            'document_metadata' => [
                // The select is gone for an organiser; a value submitted anyway
                // must not decide the type.
                ['_temp_path' => '/tmp/php1', 'path' => $path, 'titel' => 'Plattegrond', 'informatieobjecttype' => $types['bijlage']],
            ],
        ])
        ->assertHasNoTableActionErrors();

    Queue::assertPushed(
        UploadDocumentsJob::class,
        fn (UploadDocumentsJob $job): bool => $job->files[0]['informatieobjecttype'] === $types['aanvraagformulier'],
    );
});

test('an organiser upload falls back to the heuristic when the koppeling leaves the documenttype empty', function () {
    Queue::fake();
    Storage::fake('local');

    $types = fakeTwoDocumentTypes();

    $path = 'documents/organiser-file.pdf';
    Storage::put($path, '%PDF-1.4 organiser file');

    $zaak = ($this->makeZaak)();

    $this->actingAs($this->organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->callTableAction('upload', data: [
            'files' => [$path],
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'path' => $path, 'titel' => 'Plattegrond', 'informatieobjecttype' => $types['aanvraagformulier']],
            ],
        ])
        ->assertHasNoTableActionErrors();

    // No mapping row, so the omschrijving heuristic wins: the type containing
    // "bijlage", not the submitted one and not simply the first.
    Queue::assertPushed(
        UploadDocumentsJob::class,
        fn (UploadDocumentsJob $job): bool => $job->files[0]['informatieobjecttype'] === $types['bijlage'],
    );
});

test('a coordinator upload keeps the chosen documenttype', function () {
    Queue::fake();
    Storage::fake('local');

    $types = fakeTwoDocumentTypes();
    mapBijlageDocumentType($this->municipality, 'Aanvraagformulier');

    $path = 'documents/coordinator-file.pdf';
    Storage::put($path, '%PDF-1.4 coordinator file');

    $zaak = ($this->makeZaak)();

    $this->actingAs($this->coordinator);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->callTableAction('upload', data: [
            'files' => [$path],
            'vertrouwelijkheidaanduiding' => 'vertrouwelijk',
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'path' => $path, 'titel' => 'Intern advies', 'informatieobjecttype' => $types['bijlage']],
            ],
        ])
        ->assertHasNoTableActionErrors();

    Queue::assertPushed(
        UploadDocumentsJob::class,
        fn (UploadDocumentsJob $job): bool => $job->files[0]['informatieobjecttype'] === $types['bijlage'],
    );
});

test('the upload job applies the koppeling documenttype for an organiser', function () {
    Storage::fake('local');

    $types = fakeTwoDocumentTypes();
    mapBijlageDocumentType($this->municipality, 'Aanvraagformulier');

    $path = 'documents/queued-file.pdf';
    Storage::put($path, '%PDF-1.4 queued file');

    $zaak = ($this->makeZaak)();

    (new UploadDocumentsJob(
        $zaak,
        [['path' => $path, 'titel' => 'Plattegrond', 'original_name' => 'plattegrond.pdf', 'informatieobjecttype' => $types['bijlage']]],
        'openbaar',
        $this->organiser->id,
    ))->handle();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['informatieobjecttype'] === $types['aanvraagformulier']
    );
});

test('the upload job keeps the chosen documenttype for a coordinator', function () {
    Storage::fake('local');

    $types = fakeTwoDocumentTypes();
    mapBijlageDocumentType($this->municipality, 'Aanvraagformulier');

    $path = 'documents/queued-file.pdf';
    Storage::put($path, '%PDF-1.4 queued file');

    $zaak = ($this->makeZaak)();

    (new UploadDocumentsJob(
        $zaak,
        [['path' => $path, 'titel' => 'Intern advies', 'original_name' => 'advies.pdf', 'informatieobjecttype' => $types['bijlage']]],
        'vertrouwelijk',
        $this->coordinator->id,
    ))->handle();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['informatieobjecttype'] === $types['bijlage']
    );
});

test('a thread attachment from an organiser takes the documenttype from the koppeling', function () {
    Storage::fake('local');

    $types = fakeTwoDocumentTypes();
    mapBijlageDocumentType($this->municipality, 'Aanvraagformulier');

    $path = 'documents/thread-file.pdf';
    Storage::put($path, '%PDF-1.4 thread file');

    $zaak = ($this->makeZaak)();

    $this->actingAs($this->organiser);

    UploadDocumentAction::uploadDocument([
        'titel' => 'Plattegrond',
        'informatieobjecttype' => $types['bijlage'],
        'file' => $path,
        'file_name' => 'plattegrond.pdf',
    ], $zaak);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['informatieobjecttype'] === $types['aanvraagformulier']
    );
});

test('a thread attachment from a coordinator keeps the chosen documenttype', function () {
    Storage::fake('local');

    $types = fakeTwoDocumentTypes();
    mapBijlageDocumentType($this->municipality, 'Aanvraagformulier');

    $path = 'documents/thread-file.pdf';
    Storage::put($path, '%PDF-1.4 thread file');

    $zaak = ($this->makeZaak)();

    $this->actingAs($this->coordinator);

    UploadDocumentAction::uploadDocument([
        'titel' => 'Intern advies',
        'informatieobjecttype' => $types['bijlage'],
        'file' => $path,
        'file_name' => 'advies.pdf',
    ], $zaak);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->method() === 'POST'
        && $request->data()['informatieobjecttype'] === $types['bijlage']
    );
});
