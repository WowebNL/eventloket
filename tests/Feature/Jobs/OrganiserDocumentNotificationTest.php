<?php

declare(strict_types=1);

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Jobs\DocumentNotificationReceived;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Organisation;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\NewZaakDocument;
use App\ValueObjects\MessageDocument;
use App\ValueObjects\OpenNotification;
use App\ValueObjects\ZGW\Besluit;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Data\Generated\Catalogi\BesluitTypeData;

/*
 * An organiser who only sees the application files (documents tab off) is not
 * told about a document no screen shows them, because the notification names
 * the document. Everything else is notified as before.
 */

beforeEach(function () {
    Notification::fake();
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Http::preventStrayRequests();

    ZgwHttpFake::fakeBesluitinformatieobjecten();
    ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::fakeZaakinformatieobjecten();
    ZgwHttpFake::fakeStatustypen();
    ZgwHttpFake::fakeSingleZaaktype();
});

/**
 * A zaak (ZGW zaak 1) of a fresh organisation on an active municipal
 * connection with the given flags.
 *
 * @param  array<string, mixed>  $connection
 * @return array{0: Zaak, 1: User}
 */
function organiserNotificationZaak(array $connection): array
{
    $municipality = Municipality::factory()->create();

    MunicipalityZgwConnection::factory()->active()->create(array_merge([
        'municipality_id' => $municipality->id,
        'zaken_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/',
        'catalogi_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/',
        'documenten_url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/',
        'besluiten_url' => ZgwHttpFake::$baseUrl.'/besluiten/api/v1/',
        'autorisaties_url' => ZgwHttpFake::$baseUrl.'/autorisaties/api/v1/',
        'notificaties_url' => ZgwHttpFake::$baseUrl.'/notificaties/api/v1/',
    ], $connection));

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        'connection' => "gemeente_{$municipality->id}",
    ]);

    $organisation = Organisation::factory()->create(['type' => OrganisationType::Business]);
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
        'organiser_user_id' => $organiser->id,
    ]);

    // No besluiten on this zaak, so no besluit document either.
    Cache::forever("zaak.{$zaak->id}.besluiten", collect());

    return [$zaak, User::query()->findOrFail($organiser->id)];
}

/**
 * @param  array<string, mixed>  $document
 */
function dispatchOrganiserDocumentNotification(array $document, bool $isNew): void
{
    $url = ZgwHttpFake::fakeSingleDocument('1', array_merge([
        'auteur' => 'Behandelaar',
        'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
    ], $document));

    dispatch(new DocumentNotificationReceived(new OpenNotification(
        actie: $isNew ? 'create' : 'partial_update',
        kanaal: 'documenten',
        resource: 'enkelvoudiginformatieobject',
        hoofdObject: $url,
        resourceUrl: $url,
        aanmaakdatum: now()->toIso8601String(),
    ), $isNew));
}

test('the organiser is not told about a municipal document while the documents tab is off', function (bool $isNew) {
    [$zaak, $organiser] = organiserNotificationZaak(['show_bestanden_tab' => false]);
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $zaak->update(['reviewer_user_id' => $reviewer->id]);

    dispatchOrganiserDocumentNotification([], $isNew);

    Notification::assertNotSentTo($organiser, NewZaakDocument::class);
    // The notification itself did go out, so the job got as far as choosing
    // its recipients.
    Notification::assertSentTo(User::query()->findOrFail($reviewer->id), NewZaakDocument::class);
})->with([
    'new document' => [true],
    'updated document' => [false],
]);

test('the organiser is still told about a municipal document while the documents tab is on', function () {
    [, $organiser] = organiserNotificationZaak(['show_bestanden_tab' => true]);

    dispatchOrganiserDocumentNotification([], true);

    Notification::assertSentTo($organiser, NewZaakDocument::class);
});

test('the organiser is still told about a new version of the application PDF while the documents tab is off', function () {
    [, $organiser] = organiserNotificationZaak(['show_bestanden_tab' => false]);

    dispatchOrganiserDocumentNotification(['bestandsnaam' => 'aanvraagformulier.pdf', 'versie' => 2], false);

    Notification::assertSentTo($organiser, NewZaakDocument::class);
});

test('the organiser is still told about an attachment in an organiser thread while the documents tab is off', function () {
    [$zaak, $organiser] = organiserNotificationZaak(['show_bestanden_tab' => false]);

    $thread = OrganiserThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => null,
        'title' => 'Synthetische vraag',
    ]);
    Message::query()->create([
        'thread_id' => $thread->id,
        'user_id' => null,
        'body' => 'Zie bijlage',
        'documents' => [MessageDocument::make(ZgwHttpFake::documentUrl('1'), 1)->toArray()],
    ]);
    Notification::fake();

    dispatchOrganiserDocumentNotification([], true);

    Notification::assertSentTo($organiser, NewZaakDocument::class);
});

test('a municipal user is still told about a municipal document while the documents tab is off', function () {
    [$zaak] = organiserNotificationZaak(['show_bestanden_tab' => false]);
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $zaak->municipality->users()->attach($reviewer);
    $zaak->update(['reviewer_user_id' => $reviewer->id]);

    dispatchOrganiserDocumentNotification([], true);

    Notification::assertSentTo(User::query()->findOrFail($reviewer->id), NewZaakDocument::class);
});

/**
 * A reviewer assigned to the zaak, so there is always a recipient besides the
 * organiser to prove the notification round itself went through.
 */
function organiserNotificationReviewer(Zaak $zaak): User
{
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $zaak->update(['reviewer_user_id' => $reviewer->id]);

    return User::query()->findOrFail($reviewer->id);
}

function organiserNotificationBesluit(Zaak $zaak): void
{
    Cache::forever("zaak.{$zaak->id}.besluiten", collect([
        new Besluit(
            url: ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1',
            identificatie: 'BESLUIT-1',
            besluittype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/besluittypen/1',
            zaak: (string) $zaak->zgw_zaak_url,
            datum: now()->format('Y-m-d'),
            ingangsdatum: now()->format('Y-m-d'),
            verzenddatum: now()->format('Y-m-d'),
            besluittypeObject: BesluitTypeData::from([
                'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/besluittypen/1',
                'omschrijving' => 'Vergunning',
                'omschrijvingGeneriek' => 'Vergunning',
                'zaaktypen' => [ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1'],
                'informatieobjecttypen' => [ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1'],
                'toelichting' => '',
            ]),
            besluitDocumenten: collect([new Informatieobject(...ZgwHttpFake::documentBody('1', [
                'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
                'status' => 'definitief',
            ]))]),
        ),
    ]));
}

test('the organiser is told about a document of a visible besluit only while the besluiten tab is shown', function (bool $besluitenTab) {
    [$zaak, $organiser] = organiserNotificationZaak(['show_bestanden_tab' => false, 'show_besluiten_tab' => $besluitenTab]);
    $reviewer = organiserNotificationReviewer($zaak);
    organiserNotificationBesluit($zaak);

    dispatchOrganiserDocumentNotification([], true);

    $besluitenTab
        ? Notification::assertSentTo($organiser, NewZaakDocument::class)
        : Notification::assertNotSentTo($organiser, NewZaakDocument::class);
    Notification::assertSentTo($reviewer, NewZaakDocument::class);
})->with([
    'besluiten tab on' => [true],
    'besluiten tab off' => [false],
]);

test('a failure while checking the besluiten skips only the organiser and does not stop the round', function () {
    [$zaak, $organiser] = organiserNotificationZaak(['show_bestanden_tab' => false, 'show_besluiten_tab' => true]);
    $reviewer = organiserNotificationReviewer($zaak);
    Cache::forget("zaak.{$zaak->id}.besluiten");
    Http::fake([ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten*' => Http::response(['code' => 'error'], 500)]);

    dispatchOrganiserDocumentNotification([], true);

    Notification::assertNotSentTo($organiser, NewZaakDocument::class);
    Notification::assertSentTo($reviewer, NewZaakDocument::class);
});

test('a municipal document named like the application PDF is not announced to the organiser of a zaak with logged submission uploads', function () {
    [$zaak, $organiser] = organiserNotificationZaak(['show_bestanden_tab' => false]);
    $reviewer = organiserNotificationReviewer($zaak);
    activity('document')
        ->event('created')
        ->causedBy($organiser)
        ->performedOn($zaak)
        ->withProperties(['document_uuid' => 'echte-aanvraag', 'filename' => 'aanvraagformulier.pdf', 'titel' => 'Aanvraagformulier'])
        ->log('created');

    dispatchOrganiserDocumentNotification(['bestandsnaam' => 'aanvraagformulier.pdf', 'versie' => 2], false);

    Notification::assertNotSentTo($organiser, NewZaakDocument::class);
    Notification::assertSentTo($reviewer, NewZaakDocument::class);
});
