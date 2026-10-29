<?php

declare(strict_types=1);

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Shared\Resources\Zaken\Actions\NewDocumentVersionAction;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Livewire\Thread\MessageForm;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Advisory;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Threads\AdviceThread;
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
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Data\Generated\Catalogi\BesluitTypeData;

use function Pest\Livewire\livewire;

/*
 * What an advisor can do with the documents of a zaak on a connection, per
 * combination of the bestanden tab, the read-only opt-in for advisories, the
 * advisory's access to all zaken ("inzage") and an open advice question of the
 * advisory on the zaak ("vraag").
 */

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    // documenten() applies the role filter only outside the console, and the
    // suite runs through the CLI. Flip the memoized flag so the web path is
    // the one under test.
    (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);

    Http::fake([ZgwHttpFake::$baseUrl.'*' => Http::response('%PDF-1.4 synthetic bytes', 200)]);
});

function matrixDocument(string $uuid, string $vertrouwelijkheid = DocumentVertrouwelijkheden::Zaakvertrouwelijk->value): Informatieobject
{
    return new Informatieobject(
        uuid: $uuid,
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.$uuid,
        creatiedatum: now()->toIso8601String(),
        titel: 'Document '.$uuid,
        vertrouwelijkheidaanduiding: $vertrouwelijkheid,
        auteur: 'Test',
        versie: 1,
        bestandsnaam: $uuid.'.pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.$uuid.'/download',
        beschrijving: '',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );
}

/**
 * A zaak on an active municipal connection with the given flags, holding an
 * ordinary document, an attachment candidate and a confidential document.
 *
 * @param  array<string, mixed>  $connection
 */
function matrixZaak(array $connection): Zaak
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

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/'.Str::uuid(),
    ]);

    Cache::forever("zaak.{$zaak->id}.documenten", collect([
        matrixDocument('doc-gewoon'),
        matrixDocument('doc-bijlage'),
        matrixDocument('doc-geheim', DocumentVertrouwelijkheden::Confidentieel->value),
    ]));
    Cache::forever("zaak.{$zaak->id}.besluiten", collect());

    return $zaak;
}

/**
 * An advisor in a fresh advisory, optionally with access to all zaken and
 * optionally with a (non-concept) advice question on the zaak.
 *
 * @return array{0: User, 1: Advisory}
 */
function matrixAdvisor(Zaak $zaak, bool $inzage, bool $vraag): array
{
    $advisory = Advisory::factory()->create(['can_view_any_zaak' => $inzage]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisory->users()->attach($advisor, ['role' => AdvisoryRole::Member]);

    if ($vraag) {
        matrixAdviceThread($zaak, $advisory);
    }

    // The advisory "owns" doc-gewoon, so the per-document version rule
    // (DocumentVersionAuthorizer) is never the reason a version is refused.
    activity('document')
        ->event('created')
        ->causedBy($advisor)
        ->performedOn($zaak)
        ->withProperties(['document_uuid' => 'doc-gewoon', 'filename' => 'doc-gewoon.pdf'])
        ->log('created');

    return [User::query()->findOrFail($advisor->id), $advisory];
}

function matrixAdviceThread(Zaak $zaak, Advisory $advisory, AdviceStatus $status = AdviceStatus::Asked): AdviceThread
{
    return AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $advisory->id,
        'advice_status' => $status,
        'advice_due_at' => now()->addDays(7),
        'created_by' => null,
        'title' => 'Synthetische adviesvraag',
    ]);
}

function matrixAttach(AdviceThread $thread, string $uuid): void
{
    Message::query()->create([
        'thread_id' => $thread->id,
        'user_id' => null,
        'body' => 'Zie bijlage',
        'documents' => [MessageDocument::make(matrixDocument($uuid)->url, 1)->toArray()],
    ]);
}

function documentRoute(Zaak $zaak, string $uuid, string $type = 'download'): string
{
    return route('zaak.documents.view', ['zaak' => $zaak->id, 'documentuuid' => $uuid, 'type' => $type]);
}

function viewZaakAsAdvisor(User $advisor, Advisory $advisory, Zaak $zaak): mixed
{
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    test()->actingAs($advisor);
    Filament::setTenant($advisory);

    return livewire(ViewZaak::class, ['record' => $zaak->id]);
}

// [bestanden tab, read-only opt-in, inzage, vraag, tab shown, download status, upload and version allowed]
dataset('advisory document matrix', [
    'tab on, inzage and vraag' => [true, false, true, true, true, 200, true],
    'tab on, inzage only' => [true, false, true, false, true, 200, false],
    'tab on, vraag only' => [true, false, false, true, true, 200, true],
    'tab off, opt-in off, inzage and vraag' => [false, false, true, true, false, 404, false],
    'tab off, opt-in off, inzage only' => [false, false, true, false, false, 404, false],
    'tab off, opt-in off, vraag only' => [false, false, false, true, false, 404, false],
    'tab off, opt-in on, inzage and vraag' => [false, true, true, true, true, 200, false],
    'tab off, opt-in on, inzage only' => [false, true, true, false, true, 200, false],
    'tab off, opt-in on, vraag only' => [false, true, false, true, false, 404, false],
]);

test('an advisor gets the documents of a zaak according to the matrix', function (
    bool $tab,
    bool $optIn,
    bool $inzage,
    bool $vraag,
    bool $tabShown,
    int $downloadStatus,
    bool $mayWrite,
) {
    $zaak = matrixZaak(['show_bestanden_tab' => $tab, 'show_bestanden_tab_read_only_for_advisories' => $optIn]);
    [$advisor, $advisory] = matrixAdvisor($zaak, $inzage, $vraag);

    $page = viewZaakAsAdvisor($advisor, $advisory, $zaak)->assertSuccessful();
    $tabShown
        ? $page->assertSeeLivewire(ZaakDocumentsTable::class)
        : $page->assertDontSeeLivewire(ZaakDocumentsTable::class);

    $this->get(documentRoute($zaak, 'doc-gewoon'))->assertStatus($downloadStatus);

    // Upload and new version both authorise on this policy.
    expect($advisor->can('uploadDocument', $zaak->fresh()))->toBe($mayWrite);

    if ($tabShown) {
        $table = livewire(ZaakDocumentsTable::class, ['zaak' => $zaak]);

        $mayWrite
            ? $table->assertTableActionVisible('upload')->assertTableActionVisible('new-version', 'doc-gewoon')
            : $table->assertTableActionHidden('upload')->assertTableActionHidden('new-version', 'doc-gewoon');
    }
})->with('advisory document matrix');

test('an advisor without inzage and without vraag cannot open the zaak or its documents', function (bool $tab, bool $optIn) {
    $zaak = matrixZaak(['show_bestanden_tab' => $tab, 'show_bestanden_tab_read_only_for_advisories' => $optIn]);
    [$advisor] = matrixAdvisor($zaak, inzage: false, vraag: false);

    $this->actingAs($advisor)->get(documentRoute($zaak, 'doc-gewoon'))->assertForbidden();
    expect($advisor->can('uploadDocument', $zaak))->toBeFalse();
})->with([
    'tab on' => [true, false],
    'tab off, opt-in off' => [false, false],
    'tab off, opt-in on' => [false, true],
]);

test('read-only access refuses an upload and a new version that go around the hidden buttons', function () {
    $zaak = matrixZaak(['show_bestanden_tab' => false, 'show_bestanden_tab_read_only_for_advisories' => true]);
    [$advisor] = matrixAdvisor($zaak, inzage: true, vraag: true);

    $this->actingAs($advisor);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertSet('readOnly', true)
        ->assertTableActionHidden('upload')
        ->assertTableActionHidden('new-version', 'doc-gewoon');

    // The buttons are only hidden on the screen; the actions themselves refuse
    // too, so a request that calls them directly is not served either.
    expect(UploadDocumentAction::make($zaak)->isAuthorized())->toBeFalse()
        ->and(NewDocumentVersionAction::make($zaak)->isAuthorized())->toBeFalse();
});

test('the documents table cannot be loaded by an advisor without document access', function () {
    $zaak = matrixZaak(['show_bestanden_tab' => false, 'show_bestanden_tab_read_only_for_advisories' => true]);
    [$advisor] = matrixAdvisor($zaak, inzage: false, vraag: true);

    $this->actingAs($advisor);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])->assertForbidden();
});

test('read-only access keeps the vertrouwelijkheid filter of the advisor role group', function () {
    $zaak = matrixZaak(['show_bestanden_tab' => false, 'show_bestanden_tab_read_only_for_advisories' => true]);
    [$advisor] = matrixAdvisor($zaak, inzage: true, vraag: false);

    $this->actingAs($advisor);

    livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
        ->assertCanSeeTableRecords(['doc-gewoon', 'doc-bijlage'])
        ->assertCanNotSeeTableRecords(['doc-geheim']);

    $this->get(documentRoute($zaak, 'doc-gewoon'))->assertOk();
    $this->get(documentRoute($zaak, 'doc-geheim'))->assertNotFound();
});

test('the zip download is offered with read-only access and refused without document access', function () {
    $readOnly = matrixZaak(['show_bestanden_tab' => false, 'show_bestanden_tab_read_only_for_advisories' => true]);
    [$advisor] = matrixAdvisor($readOnly, inzage: true, vraag: false);

    $this->actingAs($advisor);

    livewire(ZaakDocumentsTable::class, ['zaak' => $readOnly])
        ->assertTableBulkActionVisible('download-documents');

    // A municipal user on a zaak without the tab has no document access either,
    // and is not stopped by the advisor-only refusal in mount().
    $closed = matrixZaak(['show_bestanden_tab' => false]);
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $closed->municipality->users()->attach($reviewer);

    $this->actingAs($reviewer);

    livewire(ZaakDocumentsTable::class, ['zaak' => $closed])
        ->assertTableBulkActionHidden('download-documents');
});

describe('documents another tab shows to an advisor without document access', function () {
    test('an attachment in the own advice question opens, another document does not', function () {
        $zaak = matrixZaak(['show_bestanden_tab' => false]);
        [$advisor, $advisory] = matrixAdvisor($zaak, inzage: false, vraag: false);
        matrixAttach(matrixAdviceThread($zaak, $advisory), 'doc-bijlage');

        $this->actingAs($advisor);

        $this->get(documentRoute($zaak, 'doc-bijlage', 'view'))->assertOk();
        $this->get(documentRoute($zaak, 'doc-bijlage'))->assertOk();
        $this->get(documentRoute($zaak, 'doc-gewoon'))->assertNotFound();
    });

    test('an advisor cannot attach a document to a message by setting the attachments directly', function () {
        $zaak = matrixZaak(['show_bestanden_tab' => false]);
        [$advisor, $advisory] = matrixAdvisor($zaak, inzage: false, vraag: false);
        $thread = matrixAdviceThread($zaak, $advisory);
        $thread->assignedUsers()->attach($advisor);

        $this->actingAs($advisor);
        $this->get(documentRoute($zaak, 'doc-gewoon'))->assertNotFound();

        expect(fn () => livewire(MessageForm::class, ['thread' => $thread])
            ->set('documents', [['url' => matrixDocument('doc-gewoon')->url, 'versie' => 1]])
            ->call('submit'))
            ->toThrow(CannotUpdateLockedPropertyException::class);

        expect(Message::query()->whereJsonContains('documents', [['url' => matrixDocument('doc-gewoon')->url]])->exists())->toBeFalse();
        $this->get(documentRoute($zaak, 'doc-gewoon'))->assertNotFound();
    });

    test('an attachment does not open while the advice questions tab is off', function () {
        $zaak = matrixZaak(['show_bestanden_tab' => false, 'show_adviesvragen_tab' => false]);
        [$advisor, $advisory] = matrixAdvisor($zaak, inzage: false, vraag: false);
        matrixAttach(matrixAdviceThread($zaak, $advisory), 'doc-bijlage');

        $this->actingAs($advisor)->get(documentRoute($zaak, 'doc-bijlage'))->assertNotFound();
    });

    test('an attachment in the advice question of another advisory does not open', function () {
        $zaak = matrixZaak(['show_bestanden_tab' => false]);
        [$advisor] = matrixAdvisor($zaak, inzage: false, vraag: true);

        $otherAdvisory = Advisory::factory()->create(['can_view_any_zaak' => false]);
        matrixAttach(matrixAdviceThread($zaak, $otherAdvisory), 'doc-bijlage');

        $this->actingAs($advisor)->get(documentRoute($zaak, 'doc-bijlage'))->assertNotFound();
    });

    test('an attachment in an own advice question on another zaak does not open', function () {
        $zaak = matrixZaak(['show_bestanden_tab' => false]);
        [$advisor, $advisory] = matrixAdvisor($zaak, inzage: false, vraag: true);

        $otherZaak = matrixZaak(['show_bestanden_tab' => false]);
        matrixAttach(matrixAdviceThread($otherZaak, $advisory), 'doc-bijlage');

        $this->actingAs($advisor)->get(documentRoute($zaak, 'doc-bijlage'))->assertNotFound();
    });

    test('an attachment in an own advice question that is still a concept does not open', function () {
        $zaak = matrixZaak(['show_bestanden_tab' => false]);
        [$advisor, $advisory] = matrixAdvisor($zaak, inzage: false, vraag: true);
        matrixAttach(matrixAdviceThread($zaak, $advisory, AdviceStatus::Concept), 'doc-bijlage');

        $this->actingAs($advisor)->get(documentRoute($zaak, 'doc-bijlage'))->assertNotFound();
    });

    test('a document of a visible besluit opens only while the besluiten tab is shown', function (bool $besluitenTab, int $status) {
        $zaak = matrixZaak(['show_bestanden_tab' => false, 'show_besluiten_tab' => $besluitenTab]);
        [$advisor] = matrixAdvisor($zaak, inzage: false, vraag: true);

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
                besluitDocumenten: collect([matrixDocument('doc-bijlage')]),
            ),
        ]));

        $this->actingAs($advisor)->get(documentRoute($zaak, 'doc-bijlage'))->assertStatus($status);
        $this->get(documentRoute($zaak, 'doc-gewoon'))->assertNotFound();
    })->with([
        'besluiten tab on' => [true, 200],
        'besluiten tab off' => [false, 404],
    ]);
});

describe('other roles are unchanged by read-only documents for advisories', function () {
    test('a municipal user does not get the documents tab and still opens a document through the route', function (Role $role) {
        $zaak = matrixZaak(['show_bestanden_tab' => false, 'show_bestanden_tab_read_only_for_advisories' => true]);
        $user = User::factory()->create(['role' => $role]);
        $zaak->municipality->users()->attach($user);
        $user = User::query()->findOrFail($user->id);

        Filament::setCurrentPanel(Filament::getPanel('municipality'));
        $this->actingAs($user);
        Filament::setTenant($zaak->municipality);

        livewire(ViewZaak::class, ['record' => $zaak->id])
            ->assertSuccessful()
            ->assertDontSeeLivewire(ZaakDocumentsTable::class);

        $this->get(documentRoute($zaak, 'doc-gewoon'))->assertOk();
    })->with([
        'reviewer' => Role::Reviewer,
        'coordinator' => Role::Coordinator,
        'municipality admin' => Role::MunicipalityAdmin,
    ]);

    test('a platform admin and a koppeling beheerder still open a document through the route', function (Role $role) {
        $zaak = matrixZaak(['show_bestanden_tab' => false, 'show_bestanden_tab_read_only_for_advisories' => true]);
        $user = User::factory()->create(['role' => $role]);
        $zaak->municipality->users()->attach($user);

        $this->actingAs(User::query()->findOrFail($user->id))
            ->get(documentRoute($zaak, 'doc-gewoon'))
            ->assertOk();
    })->with([
        'admin' => Role::Admin,
        'koppeling beheerder' => Role::KoppelingBeheerder,
    ]);

    test('a municipal user gets the documents tab as before while the tab is shown', function () {
        $zaak = matrixZaak(['show_bestanden_tab' => true]);
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $zaak->municipality->users()->attach($reviewer);

        Filament::setCurrentPanel(Filament::getPanel('municipality'));
        $this->actingAs(User::query()->findOrFail($reviewer->id));
        Filament::setTenant($zaak->municipality);

        livewire(ViewZaak::class, ['record' => $zaak->id])
            ->assertSuccessful()
            ->assertSeeLivewire(ZaakDocumentsTable::class);

        livewire(ZaakDocumentsTable::class, ['zaak' => $zaak])
            ->assertTableActionVisible('upload');
    });
});
