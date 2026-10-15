<?php

declare(strict_types=1);

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Livewire\Zaken\BesluitenInfolist;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\Besluit;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Data\Generated\Catalogi\BesluitTypeData;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Http::fake([ZgwHttpFake::$baseUrl.'*' => Http::response([], 200)]);

    // documenten() and besluiten() skip the role filter in console context, and
    // the test suite runs through the CLI. Flip the memoized flag so these tests
    // exercise the web-request path they are about.
    (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);

    $this->municipality = Municipality::factory()->create();

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin]);

    $this->reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($this->reviewer);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);
});

/**
 * Builds a besluitdocument value object at the given vertrouwelijkheid.
 */
function besluitDocument(string $uuid, string $titel, string $vertrouwelijkheid, ?string $status = null, ?bool $inhoudIsVervallen = null): Informatieobject
{
    return new Informatieobject(
        uuid: $uuid,
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$uuid,
        creatiedatum: now()->format('Y-m-d'),
        titel: $titel,
        vertrouwelijkheidaanduiding: $vertrouwelijkheid,
        auteur: 'Test',
        versie: 1,
        bestandsnaam: $uuid.'.pdf',
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/'.$uuid.'/download',
        beschrijving: '',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
        status: $status,
        inhoudIsVervallen: $inhoudIsVervallen,
    );
}

/**
 * Seeds the besluiten cache with a single besluit holding one zaakvertrouwelijk
 * and one vertrouwelijk besluitdocument, bypassing the ZGW HTTP layer.
 */
function seedBesluitenCache(Zaak $zaak): void
{
    seedBesluitWithDocuments($zaak, [
        besluitDocument('open-doc', 'Openbaar besluitdocument', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value),
        besluitDocument('geheim-doc', 'Vertrouwelijk besluitdocument', DocumentVertrouwelijkheden::Vertrouwelijk->value),
    ]);
}

/**
 * Seeds the besluiten cache with a single besluit holding the given
 * besluitdocumenten, bypassing the ZGW HTTP layer.
 *
 * @param  list<Informatieobject>  $documenten
 */
function seedBesluitWithDocuments(Zaak $zaak, array $documenten): void
{
    Cache::forever("zaak.{$zaak->id}.besluiten", collect([
        new Besluit(
            url: ZgwHttpFake::$baseUrl.'/besluiten/api/v1/besluiten/1',
            identificatie: 'BESLUIT-1',
            besluittype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/besluittypen/1',
            zaak: (string) $zaak->zgw_zaak_url,
            datum: now()->format('Y-m-d'),
            toelichting: 'Toelichting',
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
            besluitDocumenten: collect($documenten),
        ),
    ]));
}

test('the organiser only gets the besluitdocumenten their role may see', function () {
    seedBesluitenCache($this->zaak);

    $this->actingAs($this->organiser);

    $documenten = $this->zaak->besluiten->first()->besluitDocumenten;

    expect($documenten->pluck('uuid')->all())->toBe(['open-doc']);
});

test('a reviewer keeps all besluitdocumenten', function () {
    seedBesluitenCache($this->zaak);

    $this->actingAs($this->reviewer);

    $documenten = $this->zaak->besluiten->first()->besluitDocumenten;

    expect($documenten->pluck('uuid')->all())->toBe(['open-doc', 'geheim-doc']);
});

test('filtering for one role does not leak into the next read', function () {
    seedBesluitenCache($this->zaak);

    $this->actingAs($this->organiser);
    $this->zaak->besluiten;

    // The cached collection must be untouched: a second reader with a wider role
    // still sees everything (map() builds a new collection, each()/transform()
    // would have written the filtered list back into the cache).
    // A fresh model instance, because Eloquent caches accessor results per model.
    $this->actingAs($this->reviewer);

    expect(Zaak::findOrFail($this->zaak->id)->besluiten->first()->besluitDocumenten->pluck('uuid')->all())
        ->toBe(['open-doc', 'geheim-doc']);
});

test('the besluiten infolist does not show a vertrouwelijk document to the organiser', function () {
    seedBesluitenCache($this->zaak);

    $this->actingAs($this->organiser);

    livewire(BesluitenInfolist::class, ['zaak' => $this->zaak])
        ->assertOk()
        ->assertSee('Openbaar besluitdocument')
        ->assertDontSee('Vertrouwelijk besluitdocument');
});

test('a besluitdocument that is still in_bewerking is left out of a shown besluit', function () {
    // Same status rule as the documents tab: only established documents are
    // shown. The besluit itself stays visible through its definitief document.
    seedBesluitWithDocuments($this->zaak, [
        besluitDocument('final-doc', 'Definitief besluitdocument', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'definitief'),
        besluitDocument('draft-doc', 'Besluitdocument in bewerking', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'in_bewerking'),
    ]);

    $this->actingAs($this->reviewer);

    expect($this->zaak->besluiten)->toHaveCount(1)
        ->and($this->zaak->besluiten->first()->besluitDocumenten->pluck('uuid')->all())->toBe(['final-doc']);
});

test('a definitief besluitdocument whose content has expired is left out', function () {
    seedBesluitWithDocuments($this->zaak, [
        besluitDocument('final-doc', 'Definitief besluitdocument', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'definitief'),
        besluitDocument('expired-doc', 'Vervallen besluitdocument', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'definitief', inhoudIsVervallen: true),
    ]);

    $this->actingAs($this->reviewer);

    expect($this->zaak->besluiten->first()->besluitDocumenten->pluck('uuid')->all())->toBe(['final-doc']);

    livewire(BesluitenInfolist::class, ['zaak' => $this->zaak])
        ->assertOk()
        ->assertSee('Definitief besluitdocument')
        ->assertDontSee('Vervallen besluitdocument');
});

test('a besluit whose only document has expired content stays visible without that document', function () {
    // The document is still established, so the besluit may still be
    // published; only the expired document itself is left out.
    seedBesluitWithDocuments($this->zaak, [
        besluitDocument('expired-doc', 'Vervallen besluitdocument', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'definitief', inhoudIsVervallen: true),
    ]);

    $this->actingAs($this->reviewer);

    expect($this->zaak->besluiten)->toHaveCount(1)
        ->and($this->zaak->besluiten->first()->besluitDocumenten)->toHaveCount(0);
});

test('the status rule for besluitdocumenten also applies in console context', function () {
    // Queued jobs read the besluiten without a user; the role filter is skipped
    // there, the status rule is not (as with the documents of a zaak).
    (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, true);

    seedBesluitWithDocuments($this->zaak, [
        besluitDocument('final-doc', 'Definitief besluitdocument', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'definitief'),
        besluitDocument('draft-doc', 'Besluitdocument in bewerking', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'in_bewerking'),
    ]);

    expect($this->zaak->besluiten->first()->besluitDocumenten->pluck('uuid')->all())->toBe(['final-doc']);
});

test('the besluiten infolist does not show a besluitdocument that is still in_bewerking', function () {
    seedBesluitWithDocuments($this->zaak, [
        besluitDocument('final-doc', 'Definitief besluitdocument', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'definitief'),
        besluitDocument('draft-doc', 'Besluitdocument in bewerking', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value, 'in_bewerking'),
    ]);

    $this->actingAs($this->reviewer);

    livewire(BesluitenInfolist::class, ['zaak' => $this->zaak])
        ->assertOk()
        ->assertSee('Definitief besluitdocument')
        ->assertDontSee('Besluitdocument in bewerking');
});

test('viewing a document the role may not see returns a 404 instead of a 500', function () {
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect([
        besluitDocument('open-doc', 'Openbaar document', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value),
        besluitDocument('geheim-doc', 'Vertrouwelijk document', DocumentVertrouwelijkheden::Vertrouwelijk->value),
    ]));

    $this->actingAs($this->organiser);

    $this->get(route('zaak.documents.view', [
        'zaak' => $this->zaak->id,
        'documentuuid' => 'geheim-doc',
        'type' => 'view',
    ]))->assertNotFound();
});

test('viewing an unknown document uuid returns a 404', function () {
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect([
        besluitDocument('open-doc', 'Openbaar document', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value),
    ]));

    $this->actingAs($this->organiser);

    $this->get(route('zaak.documents.view', [
        'zaak' => $this->zaak->id,
        'documentuuid' => 'bestaat-niet',
        'type' => 'view',
    ]))->assertNotFound();
});
