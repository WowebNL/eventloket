<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Jobs\Zaak\UploadDocumentsJob;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\DocumentAudience;
use App\Services\Zgw\ZgwConnectionConfig;
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

test('the confidentiality choices on the default connection honour a visibility map', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    // This connection lets the advisor see zaakvertrouwelijk documents, which
    // the hardcoded defaults do not, and keeps the organiser on openbaar only.
    // vertrouwelijk and confidentieel both reach only the gemeente here, so they
    // collapse into a single rung keyed by the connection's gemeente maximum.
    Config::set('zgw.connections.main.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'zaakvertrouwelijk',
        Role::Reviewer->value => 'confidentieel',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->coordinator);

    expect(confidentialityOptions($zaak))->toBe([
        DocumentVertrouwelijkheden::Zaakvertrouwelijk->value => 'Gemeente, Adviseur',
        DocumentVertrouwelijkheden::Confidentieel->value => 'Gemeente',
    ]);
});

test('the confidentiality select disappears when the levels reach the same audience', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    // The municipal roles top out above every level the other groups can see, so
    // the fixed upload choices make no difference at all.
    Config::set('zgw.connections.main.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'openbaar',
        Role::Reviewer->value => 'confidentieel',
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

/**
 * Give the coordinator's municipality its own active ZGW connection carrying a
 * vertrouwelijkheid map with a maximum per role group (organisator openbaar ≤
 * adviseur beperkt_openbaar ≤ gemeente intern), and return a zaak that resolves
 * to it.
 */
function zaakOnStagingConnection(Municipality $municipality, Organisation $organisation, string $zgwZaakUrl): Zaak
{
    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
        'zaken_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/',
        'catalogi_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/',
        'documenten_url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/',
        'besluiten_url' => ZgwHttpFake::$baseUrl.'/besluiten/api/v1/',
        'notificaties_url' => ZgwHttpFake::$baseUrl.'/notificaties/api/v1/',
        'vertrouwelijkheid_map' => [
            'visibility' => [
                Role::Organiser->value => 'openbaar',
                Role::Advisor->value => 'beperkt_openbaar',
                Role::Reviewer->value => 'intern',
                Role::Coordinator->value => 'intern',
                Role::MunicipalityAdmin->value => 'intern',
                Role::ReviewerMunicipalityAdmin->value => 'intern',
            ],
            'upload_default' => [
                'system' => 'openbaar',
            ],
        ],
    ]);

    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        'municipality_id' => $municipality->id,
        'connection' => "gemeente_{$municipality->id}",
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);
}

test('the confidentiality choices are derived from a municipal connection map', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = zaakOnStagingConnection($this->municipality, $this->organisation, $zgwZaakUrl);

    $this->actingAs($this->coordinator);

    // Three meaningful, nested rungs derived from the connection's own map, keyed
    // by the maxima it configures, not the hardcoded upload choices.
    expect($zaak->zgwConnectionName())->toBe("gemeente_{$this->municipality->id}")
        ->and(confidentialityOptions($zaak))->toBe([
            'openbaar' => 'Gemeente, Adviseur, Organisator',
            'beperkt_openbaar' => 'Gemeente, Adviseur',
            'intern' => 'Gemeente',
        ]);
});

test('a coordinator upload writes the derived level chosen on a municipal connection', function () {
    Queue::fake();
    Storage::fake('local');

    $path = 'documents/coordinator-file.pdf';
    Storage::put($path, '%PDF-1.4 coordinator file');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

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
            // openbaar so the coordinator, whose visibility on this connection is
            // openbaar/beperkt_openbaar/intern, may pick this document type.
            'vertrouwelijkheidaanduiding' => 'openbaar',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ], 200),
        ZgwHttpFake::$baseUrl.'*' => Http::response([], 200),
    ]);

    $zaak = zaakOnStagingConnection($this->municipality, $this->organisation, $zgwZaakUrl);

    $this->actingAs($this->coordinator);

    // "Deel met de adviseur" is the beperkt_openbaar rung on this connection.
    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->callTableAction('upload', data: [
            'files' => [$path],
            'vertrouwelijkheidaanduiding' => 'beperkt_openbaar',
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'path' => $path, 'titel' => 'Advies', 'informatieobjecttype' => $documentTypeUrl],
            ],
        ])
        ->assertHasNoTableActionErrors();

    Queue::assertPushed(
        UploadDocumentsJob::class,
        fn (UploadDocumentsJob $job): bool => $job->vertrouwelijkheidaanduiding === 'beperkt_openbaar',
    );
});

/**
 * Fake the catalogus so a single document type at the given vertrouwelijkheid is
 * offered on the zaaktype, and let every other ZGW call answer 200.
 */
function fakeDocumentType(string $documentTypeUrl, string $vertrouwelijkheid): void
{
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
            'vertrouwelijkheidaanduiding' => $vertrouwelijkheid,
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ], 200),
        ZgwHttpFake::$baseUrl.'*' => Http::response([], 200),
    ]);
}

test('an organiser upload on a connection with maxima lands on openbaar', function () {
    Queue::fake();
    Storage::fake('local');

    $path = 'documents/organiser-file.pdf';
    Storage::put($path, '%PDF-1.4 organiser file');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    fakeDocumentType($documentTypeUrl, 'openbaar');

    $zaak = zaakOnStagingConnection($this->municipality, $this->organisation, $zgwZaakUrl);

    $this->actingAs($this->organiser);

    // An organiser never gets the select, so the connection default decides. This
    // connection configures a maximum for the organiser, so openbaar is at or
    // below every group's maximum and the upload is visible to all of them.
    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->callTableAction('upload', data: [
            'files' => [$path],
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'path' => $path, 'titel' => 'Draaiboek', 'informatieobjecttype' => $documentTypeUrl],
            ],
        ])
        ->assertHasNoTableActionErrors();

    Queue::assertPushed(
        UploadDocumentsJob::class,
        fn (UploadDocumentsJob $job): bool => $job->vertrouwelijkheidaanduiding === DocumentVertrouwelijkheden::Openbaar->value,
    );

    expect(DocumentAudience::audienceFor($zaak->zgwConnectionName(), DocumentVertrouwelijkheden::Openbaar->value))
        ->toBe(['Gemeente', 'Adviseur', 'Organisator']);
});

test('an organiser upload on the default connection stays visible to the gemeente', function () {
    Queue::fake();
    Storage::fake('local');

    $path = 'documents/organiser-file.pdf';
    Storage::put($path, '%PDF-1.4 organiser file');

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';

    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);

    fakeDocumentType($documentTypeUrl, 'zaakvertrouwelijk');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->actingAs($this->organiser);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->callTableAction('upload', data: [
            'files' => [$path],
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'path' => $path, 'titel' => 'Draaiboek', 'informatieobjecttype' => $documentTypeUrl],
            ],
        ])
        ->assertHasNoTableActionErrors();

    $pushed = null;

    Queue::assertPushed(UploadDocumentsJob::class, function (UploadDocumentsJob $job) use (&$pushed): bool {
        $pushed = $job->vertrouwelijkheidaanduiding;

        return true;
    });

    // The level an organiser upload carries here must be one the default
    // connection actually shows, or the document reaches nobody. Without a map
    // the visible sets start at zaakvertrouwelijk, so openbaar would do exactly
    // that.
    expect($pushed)->toBe(DocumentVertrouwelijkheden::Zaakvertrouwelijk->value)
        ->and(ZgwConnectionConfig::documentVisibilityForRole('main', Role::Organiser))
        ->toContain($pushed)
        ->and(ZgwConnectionConfig::documentVisibilityForRole('main', Role::Reviewer))
        ->toContain($pushed)
        ->and(DocumentAudience::audienceFor('main', $pushed))
        ->toBe(['Gemeente', 'Adviseur', 'Organisator']);
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
            Role::Organiser->value => 'openbaar',
            Role::Advisor->value => 'openbaar',
            Role::Reviewer->value => 'confidentieel',
            Role::Coordinator->value => 'confidentieel',
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
