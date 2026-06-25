<?php

/**
 * Tests voor CreateLocalZaak, specifiek het synchroon zetten van de
 * initiële statustype_url (volgnummer 1) bij aanmaak.
 *
 * Achtergrond: CreateConceptAdviceQuestions wordt op `Zaak::created`
 * gedispatcht en draait vóór de async ZGW-statusketen. Zonder een
 * statustype_url op de zaak is $zaak->statustype null, wat
 * Thread::getParticipants() liet crashen. Door de initiële statustype
 * hier al te resolven is dat venster dicht.
 *
 * Faalt de lookup, dan mag de submit niet breken: we vallen terug op
 * een lege statustype_url (de webhook vult 'm later alsnog).
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\State\FormState;
use App\EventForm\Submit\Steps\CreateLocalZaak;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaaktype;
use App\ValueObjects\OzZaak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

function makeOzZaak(): OzZaak
{
    return new OzZaak(
        uuid: 'new-1',
        url: ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/new-1',
        identificatie: 'ZAAK-2026-0001',
        zaaktype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        omschrijving: 'Test event',
        startdatum: now()->toDateString(),
        registratiedatum: now()->toDateString(),
        einddatum: null,
        einddatumGepland: null,
        uiterlijkeEinddatumAfdoening: null,
        bronorganisatie: '820151130',
        zaakgeometrie: null,
    );
}

function localZaakContext(): array
{
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $zaaktype = Zaaktype::factory()->create([
        'is_active' => true,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
    $organisation = Organisation::factory()->create();
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $user->organisations()->attach($organisation, ['role' => OrganisationRole::Admin->value]);
    $organiser = OrganiserUser::findOrFail($user->id);

    $state = new FormState(values: [
        'watIsDeNaamVanHetEvenementVergunning' => 'Test event',
    ]);

    return compact('zaaktype', 'organisation', 'organiser', 'state');
}

test('sets the initial statustype_url (volgnummer 1) on the local zaak', function () {
    $ctx = localZaakContext();

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/2', 'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1', 'omschrijving' => 'In behandeling', 'volgnummer' => 2, 'isEindstatus' => false],
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1', 'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1', 'omschrijving' => 'Ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
        ], 200),
    ]);

    $zaak = app(CreateLocalZaak::class)->execute(
        state: $ctx['state'],
        ozZaak: makeOzZaak(),
        zaaktype: $ctx['zaaktype'],
        user: $ctx['organiser'],
        organisation: $ctx['organisation'],
    );

    // Volgnummer 1 wordt gekozen, ongeacht de volgorde uit de API.
    expect($zaak->reference_data->statustype_url)
        ->toBe(ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1')
        ->and($zaak->reference_data->status_name)->toBe('Ingediend');

    // En de statustype-accessor resolvet daardoor een niet-null OzStatustype.
    expect($zaak->statustype)->not->toBeNull()
        ->and($zaak->statustype->isReceived())->toBeTrue();
});

test('falls back to empty statustype_url when the statustype lookup fails', function () {
    $ctx = localZaakContext();

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response(['error' => 'boom'], 500),
    ]);

    $zaak = app(CreateLocalZaak::class)->execute(
        state: $ctx['state'],
        ozZaak: makeOzZaak(),
        zaaktype: $ctx['zaaktype'],
        user: $ctx['organiser'],
        organisation: $ctx['organisation'],
    );

    // Submit breekt niet: zaak is aangemaakt, statustype_url leeg.
    expect($zaak->exists)->toBeTrue()
        ->and($zaak->reference_data->statustype_url)->toBe('');
});
