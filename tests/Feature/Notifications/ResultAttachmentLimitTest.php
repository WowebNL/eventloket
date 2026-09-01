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
        'public_id' => 'ZAAK-00001',
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);
});

/**
 * Builds a document value object pointing at a fakeable download url.
 */
function attachmentLimitDocument(string $uuid, string $bestandsnaam): Informatieobject
{
    return new Informatieobject(
        uuid: $uuid,
        url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.$uuid,
        creatiedatum: '2026-01-01',
        titel: $bestandsnaam,
        vertrouwelijkheidaanduiding: 'openbaar',
        auteur: 'Test',
        versie: 1,
        bestandsnaam: $bestandsnaam,
        inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.$uuid.'/download',
        beschrijving: '',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );
}

/**
 * Seeds the documenten cache and the download responses for a set of documents,
 * given as file name => size in bytes.
 *
 * @param  array<string, int>  $sizesByName
 * @return array<int, Informatieobject>
 */
function seedAttachmentDocuments(Zaak $zaak, array $sizesByName): array
{
    $documents = [];
    $responses = [];
    $index = 0;

    foreach ($sizesByName as $name => $size) {
        $document = attachmentLimitDocument('doc-'.(++$index), $name);
        $documents[] = $document;
        $responses[$document->inhoud] = Http::response(str_repeat('a', $size), 200);
    }

    Http::fake($responses);
    Cache::forever("zaak.{$zaak->id}.documenten", collect($documents));

    return $documents;
}

/**
 * @param  array<int, Informatieobject>  $documents
 */
function resultNotification(Zaak $zaak, Organisation $organisation, array $documents): Result
{
    return new Result(
        zaak: $zaak,
        tenant: $organisation,
        title: 'Aanvraag afgehandeld',
        message: '<p>Uw aanvraag is afgehandeld.</p>',
        attachmentUrls: array_map(fn (Informatieobject $document) => $document->url, $documents),
    );
}

/**
 * Regression anchor. The fixture was generated from the mail as it rendered
 * before the attachment budget existed, so a mail that stays within the budget
 * has to come out byte for byte the same. A deliberate change to the mail
 * layout means deleting tests/Fixtures/mail/result-set-with-attachments.html
 * and rerunning this test, which writes it again; anything else failing here is
 * an unintended change to what recipients get.
 */
it('renders the mail unchanged when the attachments fit within the limit', function () {
    $documents = seedAttachmentDocuments($this->zaak, [
        'plattegrond.pdf' => 1024,
        'draaiboek.pdf' => 2048,
    ]);

    $mailMessage = resultNotification($this->zaak, $this->organisation, $documents)
        ->toMail($this->organiser);

    expect($mailMessage->rawAttachments)->toHaveCount(2)
        ->and($mailMessage->rawAttachments[0]['name'])->toBe('plattegrond.pdf')
        ->and($mailMessage->rawAttachments[0]['data'])->toBe(str_repeat('a', 1024))
        ->and($mailMessage->rawAttachments[0]['options'])->toBe(['mime' => 'application/pdf'])
        ->and($mailMessage->rawAttachments[1]['name'])->toBe('draaiboek.pdf')
        ->and($mailMessage->rawAttachments[1]['data'])->toBe(str_repeat('a', 2048))
        ->and($mailMessage->rawAttachments[1]['options'])->toBe(['mime' => 'application/pdf']);

    // The zaak url carries generated uuids, so it is replaced by a placeholder
    // before the rendered body is compared byte for byte with the fixture.
    $rendered = str_replace(
        route('filament.organiser.resources.zaken.view', [
            'record' => $this->zaak,
            'tenant' => $this->organisation,
        ]),
        '{ZAAK_URL}',
        (string) $mailMessage->render(),
    );

    $fixture = base_path('tests/Fixtures/mail/result-set-with-attachments.html');

    if (! file_exists($fixture)) {
        @mkdir(dirname($fixture), 0777, true);
        file_put_contents($fixture, $rendered);
    }

    expect($rendered)->toBe(file_get_contents($fixture));
});

it('never attaches more than the configured total size', function () {
    Config::set('mail.attachments.max_total_bytes', 3000);

    $documents = seedAttachmentDocuments($this->zaak, [
        'plattegrond.pdf' => 2000,
        'draaiboek.pdf' => 2000,
        'verkeersplan.pdf' => 2000,
    ]);

    $mailMessage = resultNotification($this->zaak, $this->organisation, $documents)
        ->toMail($this->organiser);

    $total = array_sum(array_map(
        fn (array $attachment) => strlen($attachment['data']),
        $mailMessage->rawAttachments,
    ));

    expect($total)->toBeLessThanOrEqual(3000)
        ->and($mailMessage->rawAttachments)->toHaveCount(1)
        ->and($mailMessage->rawAttachments[0]['name'])->toBe('plattegrond.pdf');
});

it('keeps a document that lands exactly on the budget and drops the one byte behind it', function () {
    Config::set('mail.attachments.max_total_bytes', 3000);

    $documents = seedAttachmentDocuments($this->zaak, [
        'plattegrond.pdf' => 2000,
        'draaiboek.pdf' => 1000,
        'verkeersplan.pdf' => 1,
    ]);

    $mailMessage = resultNotification($this->zaak, $this->organisation, $documents)
        ->toMail($this->organiser);

    expect($mailMessage->rawAttachments)->toHaveCount(2)
        ->and($mailMessage->rawAttachments[0]['name'])->toBe('plattegrond.pdf')
        ->and($mailMessage->rawAttachments[1]['name'])->toBe('draaiboek.pdf');

    expect((string) $mailMessage->render())->toContain('verkeersplan.pdf');
});

it('names the omitted attachments in the mail and points to the application', function () {
    Config::set('mail.attachments.max_total_bytes', 3000);

    $documents = seedAttachmentDocuments($this->zaak, [
        'plattegrond.pdf' => 2000,
        'draaiboek.pdf' => 2000,
        'verkeersplan.pdf' => 2000,
    ]);

    $rendered = (string) resultNotification($this->zaak, $this->organisation, $documents)
        ->toMail($this->organiser)
        ->render();

    expect($rendered)
        ->toContain(__('notification/result.mail.omitted_attachments.intro', ['app_name' => config('app.name')]))
        ->toContain('draaiboek.pdf')
        ->toContain('verkeersplan.pdf')
        ->toContain(route('filament.organiser.resources.zaken.view', [
            'record' => $this->zaak,
            'tenant' => $this->organisation,
        ]));
});

it('skips a single oversized document without blocking the smaller ones behind it', function () {
    Config::set('mail.attachments.max_total_bytes', 3000);

    $documents = seedAttachmentDocuments($this->zaak, [
        'draaiboek.pdf' => 5000,
        'plattegrond.pdf' => 1000,
    ]);

    $mailMessage = resultNotification($this->zaak, $this->organisation, $documents)
        ->toMail($this->organiser);

    expect($mailMessage->rawAttachments)->toHaveCount(1)
        ->and($mailMessage->rawAttachments[0]['name'])->toBe('plattegrond.pdf');

    expect((string) $mailMessage->render())
        ->toContain('draaiboek.pdf')
        ->not->toContain('plattegrond.pdf');
});

/**
 * The file name of a document is whatever the uploader called it, and the mail
 * body is Markdown, so a name is untrusted input in a context that turns syntax
 * into markup. It has to reach the reader as literal text: a link in an
 * official mail that the application did not put there is the outcome to rule
 * out, and the same goes for images, emphasis and code spans.
 */
it('renders a file name with markdown syntax as literal text', function () {
    Config::set('mail.attachments.max_total_bytes', 1000);

    $hostileName = '[klik hier](https://example.org/elders) ![x](https://example.org/pixel.png) *nadruk* _cursief_ `code` <b>vet</b>.pdf';

    $documents = seedAttachmentDocuments($this->zaak, [
        $hostileName => 2000,
    ]);

    $rendered = (string) resultNotification($this->zaak, $this->organisation, $documents)
        ->toMail($this->organiser)
        ->render();

    // The mail theme is inlined as style attributes, so the list item carries
    // one; the assertion is on the text between the tags.
    expect($rendered)
        ->toContain('>'.e($hostileName).'</li>')
        ->not->toContain('href="https://example.org/elders"')
        ->not->toContain('src="https://example.org/pixel.png"')
        ->not->toContain('<em>nadruk</em>')
        ->not->toContain('<em>cursief</em>')
        ->not->toContain('<code>code</code>')
        ->not->toContain('<b>vet</b>');
});

it('leaves the mail without an omission notice when nothing is left out', function () {
    $documents = seedAttachmentDocuments($this->zaak, [
        'plattegrond.pdf' => 1024,
    ]);

    $rendered = (string) resultNotification($this->zaak, $this->organisation, $documents)
        ->toMail($this->organiser)
        ->render();

    expect($rendered)->not->toContain(
        __('notification/result.mail.omitted_attachments.intro', ['app_name' => config('app.name')])
    );
});

it('sends no attachments and no omission notice when the notification has none', function () {
    seedAttachmentDocuments($this->zaak, ['plattegrond.pdf' => 1024]);

    $mailMessage = (new Result(
        zaak: $this->zaak,
        tenant: $this->organisation,
        title: 'Aanvraag ingetrokken',
        message: '<p>De aanvraag is ingetrokken.</p>',
    ))->toMail($this->organiser);

    expect($mailMessage->rawAttachments)->toBe([])
        ->and((string) $mailMessage->render())->not->toContain(
            __('notification/result.mail.omitted_attachments.intro', ['app_name' => config('app.name')])
        );
});
