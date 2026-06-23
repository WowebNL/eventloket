<?php

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Jobs\DocumentNotificationReceived;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
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

test('No notification is sent for the aanvraagformulier PDF on initial upload', function () {
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentUrl = ZgwHttpFake::fakeSingleDocument('1', ['bestandsnaam' => 'aanvraagformulier.pdf']);
    ZgwHttpFake::fakeZaakinformatieobjecten();

    $organisation = Organisation::factory(['type' => OrganisationType::Business])->create();
    $users = User::factory(['role' => Role::Organiser])->createMany(2);
    $organisation->users()->attach($users, ['role' => OrganisationRole::Admin]);

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();
    Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'organisation_id' => $organisation->id,
        'zaaktype_id' => $zaaktype->id,
    ]);

    dispatch(new DocumentNotificationReceived(
        new OpenNotification(
            actie: 'create',
            kanaal: 'documenten',
            resource: 'enkelvoudiginformatieobject',
            hoofdObject: $documentUrl,
            resourceUrl: $documentUrl,
            aanmaakdatum: now(),
        ),
        true
    ));

    Notification::assertNothingSent();
});

test('No notification is sent for form bijlagen on initial upload', function () {
    $bijlageNaam = 'plattegrond.png';
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentUrl = ZgwHttpFake::fakeSingleDocument('1', ['bestandsnaam' => $bijlageNaam]);
    ZgwHttpFake::fakeZaakinformatieobjecten();

    $organisation = Organisation::factory(['type' => OrganisationType::Business])->create();
    $users = User::factory(['role' => Role::Organiser])->createMany(2);
    $organisation->users()->attach($users, ['role' => OrganisationRole::Admin]);

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();
    Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'organisation_id' => $organisation->id,
        'zaaktype_id' => $zaaktype->id,
        'form_state_snapshot' => ['values' => ['bijlageVeld' => 'livewire-tmp/'.$bijlageNaam]],
    ]);

    dispatch(new DocumentNotificationReceived(
        new OpenNotification(
            actie: 'create',
            kanaal: 'documenten',
            resource: 'enkelvoudiginformatieobject',
            hoofdObject: $documentUrl,
            resourceUrl: $documentUrl,
            aanmaakdatum: now(),
        ),
        true
    ));

    Notification::assertNothingSent();
});

test('A notification IS sent when the aanvraagformulier PDF is updated (versie 2+)', function () {
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentUrl = ZgwHttpFake::fakeSingleDocument('1', ['bestandsnaam' => 'aanvraagformulier.pdf']);
    ZgwHttpFake::fakeZaakinformatieobjecten();

    $organisation = Organisation::factory(['type' => OrganisationType::Business])->create();
    $users = User::factory(['role' => Role::Organiser])->createMany(2);
    $organisation->users()->attach($users, ['role' => OrganisationRole::Admin]);

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();
    Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'organisation_id' => $organisation->id,
        'zaaktype_id' => $zaaktype->id,
    ]);

    dispatch(new DocumentNotificationReceived(
        new OpenNotification(
            actie: 'partial_update',
            kanaal: 'documenten',
            resource: 'enkelvoudiginformatieobject',
            hoofdObject: $documentUrl,
            resourceUrl: $documentUrl,
            aanmaakdatum: now(),
        ),
        false // isNew=false => update/versie 2+
    ));

    Notification::assertSentTo($organisation->users, NewZaakDocument::class);
});

test('Advisors of a concept advice request are not notified, but advisors of a sent request are', function () {
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentUrl = ZgwHttpFake::fakeSingleDocument();
    ZgwHttpFake::fakeZaakinformatieobjecten();

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'public_id' => 'ZAAK-123',
        'zaaktype_id' => $zaaktype->id,
    ]);

    // Advisory linked through a concept advice request: must NOT be notified.
    $conceptAdvisory = Advisory::factory()->create();
    $conceptAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $conceptAdvisory->users()->attach($conceptAdvisor, ['role' => AdvisoryRole::Admin]);
    AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $conceptAdvisory->id,
        'advice_status' => AdviceStatus::Concept,
        'created_by' => null,
        'title' => 'Concept advice thread',
    ]);

    // Advisory linked through a sent (asked) advice request: must be notified.
    $askedAdvisory = Advisory::factory()->create();
    $askedAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $askedAdvisory->users()->attach($askedAdvisor, ['role' => AdvisoryRole::Admin]);
    AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $askedAdvisory->id,
        'advice_status' => AdviceStatus::Asked,
        'created_by' => null,
        'title' => 'Asked advice thread',
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

    Notification::assertNotSentTo([$conceptAdvisor], NewZaakDocument::class);
    Notification::assertSentTo([$askedAdvisor], NewZaakDocument::class);
});

test('Advisors of an advice request in a final status are no longer notified about documents', function (AdviceStatus $finalStatus) {
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();
    $documentUrl = ZgwHttpFake::fakeSingleDocument();
    ZgwHttpFake::fakeZaakinformatieobjecten();

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();
    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'public_id' => 'ZAAK-123',
        'zaaktype_id' => $zaaktype->id,
    ]);

    $advisory = Advisory::factory()->create();
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisory->users()->attach($advisor, ['role' => AdvisoryRole::Admin]);
    AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $advisory->id,
        'advice_status' => $finalStatus,
        'created_by' => null,
        'title' => 'Finalized advice thread',
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

    Notification::assertNotSentTo([$advisor], NewZaakDocument::class);
})->with([
    'approved' => AdviceStatus::Approved,
    'approved with conditions' => AdviceStatus::ApprovedWithConditions,
    'rejected' => AdviceStatus::Rejected,
    'no reaction' => AdviceStatus::NoReaction,
]);
