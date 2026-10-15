<?php

declare(strict_types=1);

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Exceptions\DocumentVersionRejectedException;
use App\Livewire\Thread\MessageForm;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\ValidationException;

use function Pest\Livewire\livewire;

/**
 * A Documenten API that refuses the lock (or the update) of a document must
 * not surface as the generic error page. The user gets a specific message,
 * the failure is reported with enough context to diagnose it, and the stored
 * upload does not stay behind on local storage.
 */
const REJECTED_DOCUMENT_UUID = 'own-doc-uuid';
const UNRELATED_STORED_FILE = 'zaken/1/aanvraagformulier.pdf';
const NEW_VERSION_CONTENT = '%PDF-1.4 updated pdf content';

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    Storage::fake('local');
    // A file on the same disk that has nothing to do with the new version.
    Storage::put(UNRELATED_STORED_FILE, '%PDF-1.4 unrelated content');

    Exceptions::fake();

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin->value]);
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => Municipality::factory()->create()->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    // The documents come from the cache, so listing them makes no ZGW call.
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect([
        new Informatieobject(
            uuid: REJECTED_DOCUMENT_UUID,
            url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.REJECTED_DOCUMENT_UUID,
            creatiedatum: now()->toIso8601String(),
            titel: 'Eigen document',
            vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            auteur: 'Test',
            versie: 1,
            bestandsnaam: 'eigen-document.pdf',
            inhoud: 'base64content',
            beschrijving: 'Test beschrijving',
            informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
            formaat: 'application/pdf',
            locked: false,
        ),
    ]));

    // The organiser uploaded the document, so they may add a version to it.
    activity('document')
        ->event('created')
        ->causedBy($this->organiser)
        ->performedOn($this->zaak)
        ->withProperties(['document_uuid' => REJECTED_DOCUMENT_UUID])
        ->log('created');

    $this->actingAs($this->organiser);
});

/**
 * Fake the Documenten API with the given lock and patch responses. The specific
 * stubs come before the catch-all, because the first matching stub wins.
 */
function fakeDocumentenApi(mixed $lock, mixed $patch): void
{
    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*/lock' => $lock,
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*/unlock' => Http::response(null, 204),
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*' => $patch,
        ZgwHttpFake::$baseUrl.'*' => Http::response([], 200),
    ]);
}

/**
 * The body a Documenten API returns when it refuses an operation to the client.
 * It carries an empty invalidParams list, which the ZGW client turns into a
 * ValidationException.
 */
function forbiddenBody(): array
{
    return [
        'type' => 'https://example.com/errors/permission_denied',
        'code' => 'permission_denied',
        'title' => 'You do not have permission to perform this action.',
        'status' => 403,
        'detail' => 'You do not have permission to perform this action.',
        'invalidParams' => [],
    ];
}

function versionData(): array
{
    return [
        'titel' => 'Bijgewerkt document',
        'file' => UploadedFile::fake()->createWithContent('new-version.pdf', NEW_VERSION_CONTENT),
    ];
}

/**
 * The upload is stored under documents/ when the form is submitted; after a
 * rejected version nothing of it may be left there.
 */
function assertUploadRemoved(): void
{
    expect(Storage::allFiles('documents'))->toBe([]);
}

function forbiddenNotification(): Notification
{
    return Notification::make()
        ->title('Nieuwe versie niet opgeslagen')
        ->body('Het zaaksysteem geeft geen toestemming om een nieuwe versie van dit document te maken. Vraag de beheerder om de koppeling met het zaaksysteem te laten nakijken.')
        ->danger()
        ->persistent();
}

function generalFailureNotification(): Notification
{
    return Notification::make()
        ->title('Nieuwe versie niet opgeslagen')
        ->body('Het zaaksysteem kon de nieuwe versie nu niet opslaan. Probeer het later opnieuw. Blijft dit gebeuren, neem dan contact op met de beheerder.')
        ->danger()
        ->persistent();
}

function sentToDocument(string $suffix, string $method): Closure
{
    return fn (Request $request): bool => $request->method() === $method
        && str_ends_with($request->url(), '/enkelvoudiginformatieobjecten/'.REJECTED_DOCUMENT_UUID.$suffix);
}

test('a refused lock shows the permission message instead of an unexpected error', function () {
    fakeDocumentenApi(
        lock: Http::response(forbiddenBody(), 403),
        patch: Http::response([], 200),
    );

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->callTableAction('new-version', REJECTED_DOCUMENT_UUID, data: versionData())
        ->assertHasNoTableActionErrors()
        ->assertNotified(forbiddenNotification())
        ->assertNotNotified('Nieuwe versie is toegevoegd');

    // Nothing was locked, so there is nothing to patch or release.
    Http::assertNotSent(sentToDocument('', 'PATCH'));
    Http::assertNotSent(sentToDocument('/unlock', 'POST'));

    Exceptions::assertReported(fn (DocumentVersionRejectedException $e): bool => $e->context() === [
        'connection' => $this->zaak->zgwConnectionName(),
        'document_uuid' => REJECTED_DOCUMENT_UUID,
        'status' => 403,
        'code' => 'permission_denied',
    ] && $e->getPrevious() instanceof ValidationException);

    assertUploadRemoved();
    expect(Activity::where('log_name', 'document')->where('event', 'updated')->count())->toBe(0);
});

test('a failing patch shows a general message and still releases the lock', function () {
    fakeDocumentenApi(
        lock: Http::response(['lock' => 'lock-string-123'], 200),
        patch: Http::response(['code' => 'error', 'title' => 'Server error'], 500),
    );

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->callTableAction('new-version', REJECTED_DOCUMENT_UUID, data: versionData())
        ->assertHasNoTableActionErrors()
        ->assertNotified(generalFailureNotification())
        ->assertNotNotified('Nieuwe versie is toegevoegd');

    // The PATCH carried the uploaded content, so the upload was stored and read.
    Http::assertSent(fn (Request $request): bool => sentToDocument('', 'PATCH')($request)
        && $request->data()['inhoud'] === base64_encode(NEW_VERSION_CONTENT));
    Http::assertSent(sentToDocument('/unlock', 'POST'));

    Exceptions::assertReported(fn (DocumentVersionRejectedException $e): bool => $e->status === 500
        && $e->errorCode === 'error'
        && $e->getPrevious() instanceof ApiRequestException);

    assertUploadRemoved();
    expect(Activity::where('log_name', 'document')->where('event', 'updated')->count())->toBe(0);
});

test('an unreachable Documenten API shows a general message', function () {
    fakeDocumentenApi(
        lock: fn () => throw new ConnectionException('Connection refused'),
        patch: Http::response([], 200),
    );

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->callTableAction('new-version', REJECTED_DOCUMENT_UUID, data: versionData())
        ->assertHasNoTableActionErrors()
        ->assertNotified(generalFailureNotification());

    Exceptions::assertReported(fn (DocumentVersionRejectedException $e): bool => $e->status === null
        && $e->errorCode === null
        && $e->getPrevious() instanceof ConnectionException);

    assertUploadRemoved();
});

test('a refused lock from the message form shows the permission message and attaches nothing', function () {
    fakeDocumentenApi(
        lock: Http::response(forbiddenBody(), 403),
        patch: Http::response([], 200),
    );

    // Creating the thread notifies the municipality; keep that out of the test.
    LaravelNotification::fake();

    $thread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test thread',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    livewire(MessageForm::class, ['thread' => $thread])
        ->callAction('attach', data: [
            'type' => 'version',
            'existing_document' => REJECTED_DOCUMENT_UUID,
            ...versionData(),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified(forbiddenNotification())
        ->assertNotNotified('Nieuwe versie toegevoegd')
        ->assertSet('documents', []);

    Http::assertNotSent(sentToDocument('', 'PATCH'));

    Exceptions::assertReported(fn (DocumentVersionRejectedException $e): bool => $e->isForbidden()
        && $e->documentUuid === REJECTED_DOCUMENT_UUID);

    assertUploadRemoved();
});

/**
 * The file field must only accept a fresh upload. A path sent as the field's
 * state must not be read as the new content, nor be deleted afterwards.
 */
test('a file path sent as the upload state is refused in the documents table', function () {
    fakeDocumentenApi(
        lock: Http::response(['lock' => 'lock-string-123'], 200),
        patch: Http::response([], 200),
    );

    livewire(ZaakDocumentsTable::class, ['zaak' => $this->zaak])
        ->callTableAction('new-version', REJECTED_DOCUMENT_UUID, data: [
            'titel' => 'Bijgewerkt document',
            'file' => [UNRELATED_STORED_FILE],
        ])
        ->assertHasTableActionErrors(['file']);

    Http::assertNotSent(sentToDocument('/lock', 'POST'));
    Http::assertNotSent(sentToDocument('', 'PATCH'));
    Storage::assertExists(UNRELATED_STORED_FILE);
});

test('a file path sent as the upload state is refused in the message form', function () {
    fakeDocumentenApi(
        lock: Http::response(['lock' => 'lock-string-123'], 200),
        patch: Http::response([], 200),
    );

    LaravelNotification::fake();

    $thread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test thread',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    livewire(MessageForm::class, ['thread' => $thread])
        ->callAction('attach', data: [
            'type' => 'version',
            'existing_document' => REJECTED_DOCUMENT_UUID,
            'titel' => 'Bijgewerkt document',
            'file' => [UNRELATED_STORED_FILE],
        ])
        ->assertHasActionErrors(['file']);

    Http::assertNotSent(sentToDocument('/lock', 'POST'));
    Http::assertNotSent(sentToDocument('', 'PATCH'));
    Storage::assertExists(UNRELATED_STORED_FILE);
});
