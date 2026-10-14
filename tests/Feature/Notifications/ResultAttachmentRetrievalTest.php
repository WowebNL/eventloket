<?php

declare(strict_types=1);

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\Result;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Exceptions\ApiRequestException;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->organiser = User::factory()->create([
        'email' => 'organiser@example.com',
        'role' => Role::Organiser,
    ]);

    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin]);

    $this->municipality = Municipality::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $this->zaak = Zaak::factory()->create([
        'public_id' => 'ZAAK-00002',
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);
});

/**
 * Seeds the documenten cache with one document and fakes the response its
 * download url gives back.
 */
function seedRetrievalDocument(Zaak $zaak, string $bestandsnaam, string $titel, $response): Informatieobject
{
    $document = new Informatieobject(
        uuid: 'retrieval-doc',
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/retrieval-doc',
        creatiedatum: '2026-01-01',
        titel: $titel,
        vertrouwelijkheidaanduiding: 'openbaar',
        auteur: 'Test',
        versie: 1,
        bestandsnaam: $bestandsnaam,
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/retrieval-doc/download',
        beschrijving: '',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );

    Http::fake([$document->inhoud.'*' => $response]);
    Cache::forever("zaak.{$zaak->id}.documenten", collect([$document]));

    return $document;
}

function retrievalNotification(Zaak $zaak, Organisation $organisation, Informatieobject $document): Result
{
    return new Result(
        zaak: $zaak,
        tenant: $organisation,
        title: 'Aanvraag afgehandeld',
        message: '<p>Uw aanvraag is afgehandeld.</p>',
        attachmentUrls: [$document->url],
    );
}

it('attaches the document bytes the api hands over', function () {
    $document = seedRetrievalDocument(
        $this->zaak,
        'plattegrond.pdf',
        'Plattegrond',
        Http::response('%PDF-1.4 real content', 200),
    );

    $mailMessage = retrievalNotification($this->zaak, $this->organisation, $document)
        ->toMail($this->organiser);

    expect($mailMessage->rawAttachments)->toHaveCount(1)
        ->and($mailMessage->rawAttachments[0]['name'])->toBe('plattegrond.pdf')
        ->and($mailMessage->rawAttachments[0]['data'])->toBe('%PDF-1.4 real content');
});

/**
 * A result mail whose attachments are incomplete is worse than no mail: the
 * recipient cannot tell that anything is missing, and neither can the handler.
 * Building the mail therefore fails rather than quietly leaving the document
 * out or attaching whatever the endpoint answered instead.
 */
it('does not build a result mail around a document the api refused to hand over', function () {
    $document = seedRetrievalDocument(
        $this->zaak,
        'plattegrond.pdf',
        'Plattegrond',
        Http::response('application/octet-stream', 406),
    );

    $notification = retrievalNotification($this->zaak, $this->organisation, $document);

    expect(fn () => $notification->toMail($this->organiser))
        ->toThrow(ApiRequestException::class);
});

/**
 * The invariant behind the test above, stated on the attachments themselves:
 * whatever the endpoint answers when it cannot serve the file must never reach
 * a recipient as the document.
 */
it('never attaches what the endpoint answered instead of the file', function () {
    $document = seedRetrievalDocument(
        $this->zaak,
        'plattegrond.pdf',
        'Plattegrond',
        Http::response('application/octet-stream', 406),
    );

    $attachmentData = [];

    try {
        $mailMessage = retrievalNotification($this->zaak, $this->organisation, $document)
            ->toMail($this->organiser);

        $attachmentData = array_column($mailMessage->rawAttachments, 'data');
    } catch (ApiRequestException) {
        // No mail was built at all, so nothing was attached either.
    }

    expect($attachmentData)->not->toContain('application/octet-stream');
});

it('reports the documents that cannot be retrieved by their title', function () {
    $document = seedRetrievalDocument(
        $this->zaak,
        'plattegrond.pdf',
        'Plattegrond',
        Http::response('application/octet-stream', 406),
    );

    expect(Result::unretrievableAttachments($this->zaak, [$document->url]))
        ->toBe(['Plattegrond']);
});

it('reports nothing to stop for when every document can be retrieved', function () {
    $document = seedRetrievalDocument(
        $this->zaak,
        'plattegrond.pdf',
        'Plattegrond',
        Http::response('%PDF-1.4 real content', 200),
    );

    expect(Result::unretrievableAttachments($this->zaak, [$document->url]))->toBe([])
        ->and(Result::unretrievableAttachments($this->zaak, null))->toBe([])
        ->and(Result::unretrievableAttachments($this->zaak, []))->toBe([]);
});
