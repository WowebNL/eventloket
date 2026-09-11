<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Jobs\Zaak\AddResultaatZGW;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create();
    $this->organisation = Organisation::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::fakeSingleZaaktype();
    // The resultaattype select labels its options by omschrijving, and the
    // selected one is read back by url, so both fakes have to carry one.
    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/resultaattypen/1' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/resultaattypen/1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
            'omschrijving' => 'Verleend',
            'omschrijvingGeneriek' => 'Afgehandeld',
            'besluittypen' => [],
        ], 200),
    ]);

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/resultaattypen*' => Http::response(ZgwHttpFake::envelope([
            [
                'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/resultaattypen/1',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'omschrijving' => 'Verleend',
                'omschrijvingGeneriek' => 'Afgehandeld',
                'besluittypen' => [],
            ],
        ]), 200),
    ]);
    // Http stubs are matched in the order they are registered, so the download
    // stub has to sit in front of the wildcard. Its response is read per test.
    $this->documentDownload = Http::response('%PDF-1.4 real content', 200);

    $test = $this;
    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/preflight-doc/download*' => fn () => $test->documentDownload,
    ]);

    ZgwHttpFake::wildcardFake();

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->document = new Informatieobject(
        uuid: 'preflight-doc',
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/preflight-doc',
        creatiedatum: '2026-01-01',
        titel: 'Plattegrond',
        vertrouwelijkheidaanduiding: 'zaakvertrouwelijk',
        auteur: 'Test',
        versie: 1,
        bestandsnaam: 'plattegrond.pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/preflight-doc/download',
        beschrijving: '',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );

    Cache::forever("zaak.{$this->zaak->id}.documenten", collect([$this->document]));

    $this->reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->reviewer->municipalities()->attach($this->municipality);

    $this->actingAs($this->reviewer);
    Filament::setTenant($this->municipality);
});

/**
 * @param  array<int, string>  $documentUrls
 */
function finishZaakData(array $documentUrls): array
{
    return [
        'result_type' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/resultaattypen/1',
        'result_has_besluit' => false,
        'result_toelichting' => 'Afgehandeld.',
        'message_title' => 'Uw aanvraag is afgerond',
        'message_content' => '<p>Uw aanvraag is afgerond.</p>',
        'message_documenten' => $documentUrls,
    ];
}

/**
 * The mail is built in a queued job, so a document that cannot be downloaded
 * there fails where the handler never sees it, after the zaak has already been
 * finished. The action therefore checks the selection first and stops.
 */
it('does not finish the zaak when a selected attachment cannot be retrieved', function () {
    Bus::fake();

    $this->documentDownload = Http::response('application/octet-stream', 406);

    livewire(ViewZaak::class, ['record' => $this->zaak->id])
        ->callAction('finish_zaak', finishZaakData([$this->document->url]))
        ->assertHasNoActionErrors()
        ->assertNotified(__('municipality/resources/zaak.header_actions.finish_zaak.unretrievable_attachments.title'));

    Bus::assertNothingDispatched();

    expect($this->zaak->fresh()->reference_data->resultaat)->toBeNull();
});

it('finishes the zaak when every selected attachment can be retrieved', function () {
    Bus::fake();

    livewire(ViewZaak::class, ['record' => $this->zaak->id])
        ->callAction('finish_zaak', finishZaakData([$this->document->url]))
        ->assertHasNoActionErrors();

    Bus::assertDispatched(AddResultaatZGW::class);
});
