<?php

declare(strict_types=1);

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak as OrganiserViewZaak;
use App\Livewire\Thread\MessageForm;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Organisation;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\MessageDocument;
use App\ValueObjects\ZGW\Besluit;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Data\Generated\Catalogi\BesluitTypeData;

use function Pest\Livewire\livewire;

/*
 * Which documents of a zaak an organiser can open through the document route
 * when the documents tab is switched off for the connection: the files
 * delivered with the application, and documents another screen shows them
 * (besluit documents, attachments in organiser threads). Anything else gets
 * the same 404 as an unknown document.
 */

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    // documenten() applies the role filter only outside the console, and the
    // suite runs through the CLI. Flip the memoized flag so the web path is
    // the one under test.
    (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);

    // No real ZGW backend: a download gets synthetic bytes, a version read
    // gets the metadata of version 1 of the requested document (a test can
    // change that version through $this->olderVersion).
    $this->olderVersion = [];
    $test = $this;
    Http::fake(function ($request) use ($test) {
        if (preg_match('#/enkelvoudiginformatieobjecten/([^/?]+)\?#', $request->url(), $match) === 1) {
            return Http::response(array_merge(organiserAccessDocument($match[1])->toArray(), ['versie' => 1], $test->olderVersion), 200);
        }

        return Http::response('%PDF-1.4 synthetic bytes', 200);
    });

    // Creating a thread notifies the municipality; keep that out of the tests.
    Notification::fake();
});

function organiserAccessDocument(string $uuid, ?string $bestandsnaam = null): Informatieobject
{
    return new Informatieobject(
        uuid: $uuid,
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.$uuid,
        creatiedatum: now()->toIso8601String(),
        titel: 'Document '.$uuid,
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        auteur: 'Test',
        versie: 2,
        bestandsnaam: $bestandsnaam ?? $uuid.'.pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.$uuid.'/download',
        beschrijving: '',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );
}

/**
 * A zaak of a fresh organisation on an active municipal connection with the
 * given flags. It holds the application PDF, a form attachment (from the
 * veiligheidsplan upload field), a municipal document, a besluit document
 * candidate and a thread attachment candidate, plus any extra documents.
 *
 * With $logged the submission uploads are logged the way the submission jobs
 * do it (a 'created' activity with the uuid, caused by the organiser); without
 * it the zaak looks like one submitted before that logging existed.
 *
 * @param  array<string, mixed>  $connection
 * @param  list<Informatieobject>  $extraDocuments
 * @param  array<string, mixed>  $extraValues
 * @return array{0: Zaak, 1: User}
 */
function organiserAccessZaak(array $connection, bool $logged = false, array $extraDocuments = [], array $extraValues = []): array
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
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/'.Str::uuid(),
        'organiser_user_id' => $organiser->id,
        'form_state_snapshot' => ['values' => array_merge([
            'veiligheidsplan' => 'event-form-uploads/'.$organisation->uuid.'/upload-1.pdf',
            'veiligheidsplan_namen' => 'situatieschets.pdf',
        ], $extraValues)],
    ]);

    Cache::forever("zaak.{$zaak->id}.documenten", collect([
        organiserAccessDocument('doc-aanvraag', 'aanvraagformulier.pdf'),
        organiserAccessDocument('doc-formulierbijlage', 'situatieschets.pdf'),
        organiserAccessDocument('doc-gemeente'),
        organiserAccessDocument('doc-besluit'),
        organiserAccessDocument('doc-bericht'),
        ...$extraDocuments,
    ]));

    if ($logged) {
        foreach (['doc-aanvraag' => 'aanvraagformulier.pdf', 'doc-formulierbijlage' => 'situatieschets.pdf'] as $uuid => $filename) {
            activity('document')
                ->event('created')
                ->causedBy($organiser)
                ->performedOn($zaak)
                ->withProperties(['document_uuid' => $uuid, 'filename' => $filename, 'titel' => $filename])
                ->log('created');
        }
    }
    Cache::forever("zaak.{$zaak->id}.besluiten", collect());

    return [$zaak, User::query()->findOrFail($organiser->id)];
}

function organiserAccessBesluit(Zaak $zaak, string $documentUuid): void
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
            besluitDocumenten: collect([organiserAccessDocument($documentUuid)]),
        ),
    ]));
}

function organiserAccessThreadAttachment(Zaak $zaak, string $documentUuid, ThreadType $type = ThreadType::Organiser): void
{
    $thread = OrganiserThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => $type,
        'created_by' => null,
        'title' => 'Synthetische vraag',
    ]);

    Message::query()->create([
        'thread_id' => $thread->id,
        'user_id' => null,
        'body' => 'Zie bijlage',
        'documents' => [MessageDocument::make(organiserAccessDocument($documentUuid)->url, 2)->toArray()],
    ]);
}

function organiserAccessRoute(Zaak $zaak, string $uuid, string $type = 'view', ?int $version = null): string
{
    return route('zaak.documents.view', array_filter([
        'zaak' => $zaak->id,
        'documentuuid' => $uuid,
        'type' => $type,
        'version' => $version,
    ]));
}

describe('documents tab off', function () {
    test('a municipal document is not found, for view, download and an older version', function (string $type, ?int $version) {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);

        $this->actingAs($organiser)
            ->get(organiserAccessRoute($zaak, 'doc-gemeente', $type, $version))
            ->assertNotFound();

        // Refused before anything is fetched from the documents API.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'doc-gemeente'));
    })->with([
        'view' => ['view', null],
        'download' => ['download', null],
        'older version' => ['download', 1],
    ]);

    test('the refusal looks the same as an unknown document', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);

        $hidden = $this->actingAs($organiser)->get(organiserAccessRoute($zaak, 'doc-gemeente'));
        $unknown = $this->get(organiserAccessRoute($zaak, 'doc-bestaat-niet'));

        $hidden->assertNotFound();
        $unknown->assertNotFound();
        expect($hidden->getContent())->toBe($unknown->getContent());
    });

    test('the application PDF and the form attachments still open', function (string $uuid) {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, $uuid))->assertOk();
        $this->get(organiserAccessRoute($zaak, $uuid, 'download'))->assertOk();
        $this->get(organiserAccessRoute($zaak, $uuid, 'download', 1))->assertOk();
    })->with([
        'application PDF' => ['doc-aanvraag'],
        'form attachment' => ['doc-formulierbijlage'],
    ]);

    test('a document of a visible besluit opens while the besluiten tab is shown', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false, 'show_besluiten_tab' => true]);
        organiserAccessBesluit($zaak, 'doc-besluit');

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, 'doc-besluit'))->assertOk();
    });

    test('a besluit document does not open while the besluiten tab is hidden', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false, 'show_besluiten_tab' => false]);
        organiserAccessBesluit($zaak, 'doc-besluit');

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, 'doc-besluit'))->assertNotFound();
    });

    test('an attachment to a message in an organiser thread of the zaak still opens, also an older version', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);
        organiserAccessThreadAttachment($zaak, 'doc-bericht');

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, 'doc-bericht'))->assertOk();
        $this->get(organiserAccessRoute($zaak, 'doc-bericht', 'download'))->assertOk();
        $this->get(organiserAccessRoute($zaak, 'doc-bericht', 'view', 1))->assertOk();
    });

    test('an attachment in an advice thread or in a thread of another zaak does not open', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);
        organiserAccessThreadAttachment($zaak, 'doc-bericht', ThreadType::Advice);

        [$otherZaak] = organiserAccessZaak(['show_bestanden_tab' => false]);
        organiserAccessThreadAttachment($otherZaak, 'doc-gemeente');

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, 'doc-bericht'))->assertNotFound();
        $this->get(organiserAccessRoute($zaak, 'doc-gemeente'))->assertNotFound();
    });
});

describe('documents tab on', function () {
    test('every document the organiser may see opens as before', function (string $uuid) {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => true]);

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, $uuid))->assertOk();
        $this->get(organiserAccessRoute($zaak, $uuid, 'download', 1))->assertOk();
    })->with(['doc-aanvraag', 'doc-gemeente', 'doc-besluit', 'doc-bericht']);
});

describe('other roles', function () {
    test('a municipal user still opens a municipal document with the documents tab off', function () {
        [$zaak] = organiserAccessZaak(['show_bestanden_tab' => false]);
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $zaak->municipality->users()->attach($reviewer);

        $this->actingAs(User::query()->findOrFail($reviewer->id))
            ->get(organiserAccessRoute($zaak, 'doc-gemeente'))
            ->assertOk();
    });
});

describe('recognising the application files', function () {
    test('on a zaak with logged submission uploads the own application files open', function (string $uuid) {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false], logged: true);

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, $uuid))->assertOk();
    })->with(['doc-aanvraag', 'doc-formulierbijlage']);

    test('a municipal document that only shares a name with an application file is not found and not listed', function (Informatieobject $document, array $values) {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false], logged: true, extraDocuments: [$document], extraValues: $values);

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, $document->uuid))->assertNotFound();

        livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
            ->assertSee('Document doc-aanvraag')
            ->assertDontSee('Document '.$document->uuid);
    })->with([
        'the name of a form attachment' => fn () => [organiserAccessDocument('doc-naamgenoot', 'situatieschets.pdf'), []],
        'the name of the application PDF' => fn () => [organiserAccessDocument('doc-intern', 'aanvraagformulier.pdf'), []],
        'no file name, with a form value of a bare separator' => fn () => [organiserAccessDocument('doc-zonder-naam', ''), ['veiligheidsplan' => '/']],
    ]);

    test('a later upload by the organiser does not count as an application file', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false], logged: true, extraDocuments: [organiserAccessDocument('doc-later')]);

        // The upload action logs the vertrouwelijkheidaanduiding as well; the
        // submission jobs do not.
        activity('document')
            ->event('created')
            ->causedBy($organiser)
            ->performedOn($zaak)
            ->withProperties([
                'document_uuid' => 'doc-later',
                'filename' => 'doc-later.pdf',
                'titel' => 'doc-later.pdf',
                'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            ])
            ->log('created');

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, 'doc-later'))->assertNotFound();
    });

    test('on a zaak without logged submission uploads only file upload values count, never an empty name', function (Informatieobject $document, array $values) {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false], extraDocuments: [$document], extraValues: $values);

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, $document->uuid))->assertNotFound();
    })->with([
        'the value of a text field' => fn () => [organiserAccessDocument('doc-notulen', 'notulen.pdf'), ['watIsDeNaamVanHetEvenement' => 'notulen.pdf']],
        'no file name, with a form value of a bare separator' => fn () => [organiserAccessDocument('doc-zonder-naam', ''), ['veiligheidsplan' => '/']],
    ]);
});

describe('older versions', function () {
    test('a draft older version of a document the organiser may open is not found', function (string $status) {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);
        organiserAccessThreadAttachment($zaak, 'doc-bericht');
        $this->olderVersion = ['status' => $status];

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, 'doc-bericht', 'view', 1))->assertNotFound();
    })->with(['in_bewerking', 'ter_vaststelling']);

    test('a definitief older version still opens', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);
        organiserAccessThreadAttachment($zaak, 'doc-bericht');
        $this->olderVersion = ['status' => 'definitief'];

        $this->actingAs($organiser)->get(organiserAccessRoute($zaak, 'doc-bericht', 'view', 1))->assertOk();
    });
});

describe('documents table', function () {
    test('mounted as organiser without the submission-only flag it still only lists the application files', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);

        $this->actingAs($organiser);

        livewire(ZaakDocumentsTable::class, ['zaak' => $zaak, 'submissionOnly' => false])
            ->assertSet('submissionOnly', true)
            ->assertSee('Document doc-aanvraag')
            ->assertDontSee('Document doc-gemeente');
    });

    test('the organiser zaak page wires the documents table in submission-only mode', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);

        Filament::setCurrentPanel(Filament::getPanel('organiser'));
        $this->actingAs($organiser);
        Filament::setTenant($zaak->organisation);

        livewire(OrganiserViewZaak::class, ['record' => $zaak->id])
            ->assertSuccessful()
            ->assertSeeLivewire(ZaakDocumentsTable::class);

        livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])->assertSet('submissionOnly', true);
    });
});

describe('message form', function () {
    test('choosing a document outside the version options returns no title', function () {
        [$zaak, $organiser] = organiserAccessZaak(['show_bestanden_tab' => false]);

        $thread = OrganiserThread::forceCreate([
            'zaak_id' => $zaak->id,
            'type' => ThreadType::Organiser,
            'created_by' => $organiser->id,
            'title' => 'Synthetische vraag',
        ]);

        // The organiser's own upload is in the version options.
        activity('document')
            ->event('created')
            ->causedBy($organiser)
            ->performedOn($zaak)
            ->withProperties(['document_uuid' => 'doc-bericht', 'filename' => 'doc-bericht.pdf'])
            ->log('created');

        Filament::setCurrentPanel(Filament::getPanel('organiser'));
        $this->actingAs($organiser);
        Filament::setTenant($zaak->organisation);

        livewire(MessageForm::class, ['thread' => $thread])
            ->mountAction('attach')
            ->setActionData(['type' => 'version'])
            ->setActionData(['existing_document' => 'doc-gemeente'])
            ->assertActionDataSet(['titel' => null])
            ->setActionData(['existing_document' => 'doc-bericht'])
            ->assertActionDataSet(['titel' => 'Document doc-bericht']);
    });
});
