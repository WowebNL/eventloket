<?php

/**
 * Integratie-test voor de complete submit-keten in één klap:
 *
 *   FormState + ingelogde user + organisation
 *          ↓  SubmitEventForm::execute()
 *   - ResolveZaaktype (gemeente × aard)
 *   - CreateZaakInZGW   (HTTP POST naar OpenZaak — gefaked)
 *   - CreateLocalZaak   (rij in `zaken`-tabel)
 *   - DraftStore::delete() van het actieve concept
 *   - Audit-log regel
 *   - Async dispatch van 6 ZGW-jobs + PDF + bijlagen
 *
 * De test fake't alle HTTP-calls naar OpenZaak zodat er geen echte
 * OpenZaak-container nodig is. Laravel's Bus/Queue/Log/Http/Storage
 * helpers doen de rest.
 *
 * Waarom deze test: unit-tests dekken de individuele stukjes, maar de
 * lijm tussen ResolveZaaktype → CreateZaakInZGW → CreateLocalZaak →
 * dispatch gaat pas echt fout als je 'm als geheel probeert. Deze
 * test waarschuwt zodra iemand iets in de keten breekt.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\State\FormState;
use App\EventForm\Submit\SubmitEventForm;
use App\Jobs\Submit\GenerateSubmissionPdf;
use App\Jobs\Submit\HashIdentifyingAttributes;
use App\Jobs\Submit\UploadFormBijlagenToZGW;
use App\Jobs\Zaak\AddEinddatumZGW;
use App\Jobs\Zaak\AddGeometryZGW;
use App\Jobs\Zaak\AddGlobaleLocatieZGW;
use App\Jobs\Zaak\AddZaakeigenschappenZGW;
use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Jobs\Zaak\SetInitialStatusZGW;
use App\Jobs\Zaak\UpdateInitiatorZGW;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

/**
 * Bouwt een ingevulde buurtfeest-state + gemeente/zaaktype/user/org
 * zodat de keten van begin tot eind gerund kan worden.
 */
function scenarioBuurtfeestInHeerlen(): array
{
    $heerlen = Municipality::factory()->create([
        'name' => 'Heerlen',
        'brk_identification' => 'GM0917',
    ]);
    $zaaktype = Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
    $organisation = Organisation::factory()->create(['name' => 'Media Tuin']);
    /** @var OrganiserUser $user */
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $user->organisations()->attach($organisation, ['role' => OrganisationRole::Admin->value]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
        'soortEvenement' => 'Buurtfeest',
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
        'aantalVerwachteAanwezigen' => 80,
        'risicoClassificatie' => 'A',
    ]);

    return compact('heerlen', 'zaaktype', 'organisation', 'user', 'state');
}

/**
 * Fake't de OpenZaak-create-endpoint zodat CreateZaakInZGW een bruikbaar
 * antwoord terugkrijgt.
 */
function fakeOpenzaakZaakCreate(): string
{
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/new-1';
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken' => Http::response([
            'url' => $zaakUrl,
            'uuid' => 'new-1',
            'identificatie' => 'ZAAK-2026-0001',
            'bronorganisatie' => '820151130',
            'startdatum' => now()->toDateString(),
            'registratiedatum' => now()->toDateString(),
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
            'status' => null,
            'einddatum' => null,
            'einddatumGepland' => null,
            'uiterlijkeEinddatumAfdoening' => null,
            'zaakgeometrie' => null,
            'betrokkene' => [],
            'resultaat' => null,
            'doelorganisatie' => null,
            'toelichting' => '',
            'omschrijving' => 'Buurtfeest Testlaan',
        ], 201),
        // CreateLocalZaak resolves the initial statustype (volgnummer 1) so the
        // local zaak has a valid statustype_url from creation.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1', 'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1', 'omschrijving' => 'Ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/2', 'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1', 'omschrijving' => 'In behandeling', 'volgnummer' => 2, 'isEindstatus' => false],
        ]), 200),
    ]);

    return $zaakUrl;
}

test('happy-path: lokale Zaak, ZGW-URL, draft leeg, async keten dispatched', function () {
    $sc = scenarioBuurtfeestInHeerlen();
    $expectedZaakUrl = fakeOpenzaakZaakCreate();

    // Vul een bestaande draft zodat we kunnen verifiëren dat 'ie wordt
    // verwijderd na submit — en een tweede (parallel) concept dat moet
    // blijven staan.
    $activeDraft = Draft::create([
        'user_id' => $sc['user']->id,
        'organisation_id' => $sc['organisation']->id,
        'state' => $sc['state']->toSnapshot(),
    ]);
    $otherDraft = Draft::create([
        'user_id' => $sc['user']->id,
        'organisation_id' => $sc['organisation']->id,
        'state' => FormState::empty()->toSnapshot(),
    ]);

    Bus::fake();

    $zaak = app(SubmitEventForm::class)->execute(
        $sc['state'],
        $sc['user'],
        $sc['organisation'],
        $activeDraft,
    );

    // 1. Lokale Zaak is aangemaakt met verwijzing naar de ZGW-zaak.
    expect($zaak)->toBeInstanceOf(Zaak::class)
        ->and($zaak->public_id)->toBe('ZAAK-2026-0001')
        ->and($zaak->zgw_zaak_url)->toBe($expectedZaakUrl)
        ->and($zaak->zaaktype_id)->toBe($sc['zaaktype']->id)
        ->and($zaak->organisation_id)->toBe($sc['organisation']->id)
        ->and($zaak->organiser_user_id)->toBe($sc['user']->id)
        ->and($zaak->data_object_url)->toBeNull(); // Objects API is weg

    // 2. ReferenceData-VO bevat de FormState-waarden.
    expect($zaak->reference_data->naam_evenement)->toBe('Buurtfeest Testlaan')
        ->and($zaak->reference_data->types_evenement)->toBe('Buurtfeest')
        ->and($zaak->reference_data->status_name)->toBe('Ingediend');

    // 2b. De initiële statustype_url (volgnummer 1) is al bij aanmaak gezet,
    //     zodat $zaak->statustype niet null is in het venster vóór de async
    //     ZGW-statusupdate. Voorkomt de crash in Thread::getParticipants().
    expect($zaak->reference_data->statustype_url)
        ->toBe(ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1');

    // 3. form_state_snapshot is opgeslagen voor latere prefill / jobs.
    expect($zaak->form_state_snapshot)->toBeArray()
        ->and($zaak->form_state_snapshot['values']['watIsDeNaamVanHetEvenementVergunning'])
        ->toBe('Buurtfeest Testlaan');

    // 4. Alleen het ingediende concept is verwijderd; het parallelle
    //    concept van dezelfde gebruiker blijft staan.
    expect(Draft::whereKey($activeDraft->id)->exists())->toBeFalse()
        ->and(Draft::whereKey($otherDraft->id)->exists())->toBeTrue();

    // 5. De jobs zitten samen in één Bus::chain() in de juiste volgorde.
    //    GenerateSubmissionPdf staat als eerste zodat de bevestigingsmail zo
    //    snel mogelijk verstuurd wordt. HashIdentifyingAttributes loopt als
    //    allerlaatste zodat alle eerdere jobs de plain BSN/KvK kunnen lezen.
    Bus::assertChained([
        GenerateSubmissionPdf::class,
        SetInitialStatusZGW::class,
        AddZaakeigenschappenZGW::class,
        AddEinddatumZGW::class,
        UpdateInitiatorZGW::class,
        AddGeometryZGW::class,
        AddGlobaleLocatieZGW::class,
        CreateDoorkomstZaken::class,
        HashIdentifyingAttributes::class,
    ]);

    // 6. Bijlagen-upload draait onafhankelijk (niet in de ketting).
    Bus::assertDispatched(UploadFormBijlagenToZGW::class,
        fn (UploadFormBijlagenToZGW $job) => $job->zaak->is($zaak)
    );

    // 7. HashIdentifyingAttributes is NIET los gedispatcht — zit in de chain.
    Bus::assertNotDispatched(HashIdentifyingAttributes::class);
});

test('geen gemeente in state → runtime-exception, géén lokale Zaak aangemaakt', function () {
    $sc = scenarioBuurtfeestInHeerlen();
    $sc['state'] = new FormState(values: [
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest zonder gemeente',
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
    ]); // evenementInGemeente ontbreekt

    fakeOpenzaakZaakCreate();

    expect(fn () => app(SubmitEventForm::class)->execute(
        $sc['state'],
        $sc['user'],
        $sc['organisation'],
    ))->toThrow(RuntimeException::class, 'Geen gemeente herleidbaar');

    // Belangrijk: geen wees-rij in de lokale DB.
    expect(Zaak::count())->toBe(0);
});

test('OpenZaak faalt → DB-transactie gerold, geen lokale Zaak', function () {
    $sc = scenarioBuurtfeestInHeerlen();

    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken' => Http::response(
            ['error' => 'internal server error'], 500
        ),
    ]);

    // De OpenZaak-client gooit bij een 5xx een exception; SubmitEventForm
    // mag die laten bubbelen. Wat belangrijk is: de transactie is gerold,
    // dus er staat geen losse Zaak-rij in onze DB.
    try {
        app(SubmitEventForm::class)->execute(
            $sc['state'],
            $sc['user'],
            $sc['organisation'],
        );
        $this->fail('Verwachtte een exception door OpenZaak 500');
    } catch (Throwable $e) {
        // verwacht — exception-type laten we open; kan RequestException
        // of InvalidArgument zijn afhankelijk van de client-versie.
    }

    expect(Zaak::count())->toBe(0);
});
