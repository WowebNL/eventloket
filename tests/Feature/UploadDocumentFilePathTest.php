<?php

declare(strict_types=1);

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Jobs\Zaak\UploadDocumentsJob;
use App\Livewire\Thread\MessageForm;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

/**
 * The file fields of the "new document" uploads must only accept a file that
 * was uploaded in the same request. A path to a file that already sits on the
 * disk must not be read, sent to the Documenten API as document content, or
 * deleted afterwards.
 */
const STORED_FILE_ELSEWHERE = 'elsewhere/unrelated.pdf';
const STORED_FILE_ELSEWHERE_CONTENT = '%PDF-1.4 content of a file that was not uploaded here';
const FRESH_UPLOAD_CONTENT = '%PDF-1.4 content of the fresh upload';

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    Storage::fake('local');
    // A file on the same disk that has nothing to do with this upload.
    Storage::put(STORED_FILE_ELSEWHERE, STORED_FILE_ELSEWHERE_CONTENT);

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin->value]);
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => Municipality::factory()->create()->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
    $this->zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $this->zgwZaakUrl,
    ]);

    // Listing the documents of the zaak makes no ZGW call.
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect());

    fakeUploadPathDocumentenApi();

    $this->actingAs($this->organiser);
});

/**
 * Fake the catalogus and the Documenten and Zaken APIs, so an upload that is
 * accepted runs to the end. The specific stubs come before the catch-all,
 * because the first matching stub wins.
 */
function fakeUploadPathDocumentenApi(): void
{
    $documentTypeUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1';
    $documentUrl = ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/new-doc-1';

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
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ], 200),
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::response([
            'url' => $documentUrl,
            'uuid' => 'new-doc-1',
            'identificatie' => 'DOC-001',
            'titel' => 'Bijlage',
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            'auteur' => 'Test',
            'versie' => 1,
            'bestandsnaam' => 'bijlage.pdf',
            'inhoud' => '',
            'beschrijving' => '',
            'formaat' => 'application/pdf',
            'locked' => false,
            'creatiedatum' => now()->format('Y-m-d'),
            'informatieobjecttype' => $documentTypeUrl,
            'indicatieGebruiksrecht' => false,
        ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
            'informatieobject' => $documentUrl,
        ], 201),
    ]);
    ZgwHttpFake::wildcardFake();
}

function documentStoredWith(string $content): Closure
{
    return fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten')
        && ($request->data()['inhoud'] ?? null) === base64_encode($content);
}

function documentStored(): Closure
{
    return fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten');
}

function uploadPathThread(Zaak $zaak, User $organiser): OrganiserThread
{
    return OrganiserThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $organiser->id,
        'title' => 'Test thread',
    ]);
}

test('a file path sent as the upload state is refused in the documents table', function () {
    Queue::fake();

    $component = livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->callTableAction('upload', data: [
            'files' => [STORED_FILE_ELSEWHERE],
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'titel' => 'Bijlage'],
            ],
        ]);

    Queue::assertNotPushed(UploadDocumentsJob::class);
    Storage::assertExists(STORED_FILE_ELSEWHERE);
    $component->assertHasTableActionErrors(['files']);
});

/**
 * The files field is required, but an entry that is empty once the form is
 * submitted still counts as a value. The action must not then look for a path
 * anywhere else in the submitted data.
 */
test('a file path sent in the document metadata is not used in the documents table', function (mixed $emptyEntry) {
    Queue::fake();

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->callTableAction('upload', data: [
            'files' => [$emptyEntry],
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'path' => STORED_FILE_ELSEWHERE, 'titel' => 'Bijlage'],
            ],
        ]);

    Queue::assertNotPushed(
        UploadDocumentsJob::class,
        fn (UploadDocumentsJob $job): bool => collect($job->files)->contains('path', STORED_FILE_ELSEWHERE),
    );
    Storage::assertExists(STORED_FILE_ELSEWHERE);
})->with([
    'an empty string' => [''],
    'false' => [false],
]);

test('a fresh upload is accepted in the documents table', function () {
    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->callTableAction('upload', data: [
            'files' => [UploadedFile::fake()->createWithContent('bijlage.pdf', FRESH_UPLOAD_CONTENT)],
            'document_metadata' => [
                ['_temp_path' => '/tmp/php1', 'titel' => 'Bijlage'],
            ],
        ])
        ->assertHasNoTableActionErrors();

    Http::assertSent(documentStoredWith(FRESH_UPLOAD_CONTENT));
    Http::assertNotSent(documentStoredWith(STORED_FILE_ELSEWHERE_CONTENT));
    expect(Storage::allFiles('documents'))->toBe([]);
    Storage::assertExists(STORED_FILE_ELSEWHERE);
});

test('a file path sent as the upload state is refused in the message form', function () {
    LaravelNotification::fake();

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    $component = livewire(MessageForm::class, ['thread' => uploadPathThread($this->zaak, $this->organiser)])
        ->callAction('attach', data: [
            'type' => 'new',
            'titel' => 'Bijlage',
            'file' => [STORED_FILE_ELSEWHERE],
        ]);

    Http::assertNotSent(documentStored());
    Storage::assertExists(STORED_FILE_ELSEWHERE);
    $component->assertHasActionErrors(['file']);
});

test('a fresh upload is accepted in the message form', function () {
    LaravelNotification::fake();

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    livewire(MessageForm::class, ['thread' => uploadPathThread($this->zaak, $this->organiser)])
        ->callAction('attach', data: [
            'type' => 'new',
            'titel' => 'Bijlage',
            'file' => UploadedFile::fake()->createWithContent('bijlage.pdf', FRESH_UPLOAD_CONTENT),
        ])
        ->assertHasNoActionErrors();

    Http::assertSent(documentStoredWith(FRESH_UPLOAD_CONTENT));
    expect(Storage::allFiles('documents'))->toBe([]);
    Storage::assertExists(STORED_FILE_ELSEWHERE);
});
