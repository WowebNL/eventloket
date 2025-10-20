<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Jobs\DocumentNotificationReceived;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\NewZaakDocument;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Notification::fake();
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->newDocumentNotification = new OpenNotification(
        actie: 'create',
        kanaal: 'documenten',
        resource: 'enkelvoudiginformatieobject',
        hoofdObject: 'https://example.com/enkelvoudiginformatieobject/123',
        resourceUrl: 'https://example.com/enkelvoudiginformatieobject/123',
        aanmaakdatum: now(),
    );

    $this->updateDocumentNotification = new OpenNotification(
        actie: 'partial_update',
        kanaal: 'documenten',
        resource: 'enkelvoudiginformatieobject',
        hoofdObject: 'https://example.com/enkelvoudiginformatieobject/123',
        resourceUrl: 'https://example.com/enkelvoudiginformatieobject/123',
        aanmaakdatum: now(),
    );

});

test('No users are notified if the zaak does not exist', function () {
    ZgwHttpFake::fakeSingleZaak();
    $url = ZgwHttpFake::fakeSingleDocument();
    ZgwHttpFake::fakeZaakinformatieobjecten();

    $newDocumentNotification = new OpenNotification(
        actie: 'create',
        kanaal: 'documenten',
        resource: 'enkelvoudiginformatieobject',
        hoofdObject: $url,
        resourceUrl: $url,
        aanmaakdatum: now(),
    );

    dispatch(new DocumentNotificationReceived($newDocumentNotification, true));
    Notification::assertNothingSent();

});

test('Organisation users are notified on new document', function () {
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentUrl = ZgwHttpFake::fakeSingleDocument();
    ZgwHttpFake::fakeZaakinformatieobjecten();

    $organisation = Organisation::factory([
        'type' => OrganisationType::Business,
    ])->create();
    $users = User::factory(['role' => Role::Organiser])->createMany(3);
    $organisation->users()->attach($users, ['role' => OrganisationRole::Admin]);

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'public_id' => 'ZAAK-123',
        'organisation_id' => $organisation->id,
        'zaaktype_id' => $zaaktype->id,
    ]);

    $newDocumentNotification = new OpenNotification(
        actie: 'create',
        kanaal: 'documenten',
        resource: 'enkelvoudiginformatieobject',
        hoofdObject: $documentUrl,
        resourceUrl: $documentUrl,
        aanmaakdatum: now(),
    );

    dispatch(new DocumentNotificationReceived($newDocumentNotification, true));

    Notification::assertSentTo($organisation->users, NewZaakDocument::class);

});
