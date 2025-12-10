<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\NotificationPreference;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\NewZaak;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->advisory = Advisory::factory()->create();
    $this->advisory->municipalities()->attach($this->municipality);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    // Create organiser user
    $this->organiser = User::factory()->create([
        'email' => 'organiser@example.com',
        'role' => Role::Organiser,
    ]);

    $this->organisation = Organisation::factory()->create([
        'type' => 'business',
        'name' => 'Test Organisation',
    ]);

    $this->organisation->users()->attach($this->organiser, [
        'role' => OrganisationRole::Admin,
    ]);

    // Create reviewer users with different roles
    $this->reviewer = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::Reviewer,
    ]);

    $this->reviewerMunicipalityAdmin = User::factory()->create([
        'email' => 'reviewer-admin@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);

    $this->municipalityAdmin = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    // Attach users to municipality
    $this->municipality->users()->attach($this->reviewer);
    $this->municipality->users()->attach($this->reviewerMunicipalityAdmin);
    $this->municipality->users()->attach($this->municipalityAdmin);
});

test('when zaak is created, organiser who created it and reviewer users should be notified', function () {
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'organiser_user_id' => $this->organiser->id,
        'reference_data' => new ZaakReferenceData(
            risico_classificatie: 'B',
            start_evenement: now(),
            eind_evenement: now()->addDay(),
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test Event'
        ),
    ]);

    // Organiser who created the zaak should be notified
    Notification::assertSentTo(
        [$this->organiser],
        NewZaak::class,
        function (NewZaak $notification, $channels) {
            return in_array('mail', $channels) && in_array('database', $channels);
        }
    );

    // Reviewer and ReviewerMunicipalityAdmin should be notified
    Notification::assertSentTo(
        [$this->reviewer, $this->reviewerMunicipalityAdmin],
        NewZaak::class,
        function (NewZaak $notification, $channels) {
            return in_array('mail', $channels) && in_array('database', $channels);
        }
    );

    // MunicipalityAdmin should NOT be notified
    Notification::assertNotSentTo(
        [$this->municipalityAdmin],
        NewZaak::class
    );
});

test('when zaak is created without organiser_user_id, only reviewer users should be notified', function () {
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'organiser_user_id' => null,
        'reference_data' => new ZaakReferenceData(
            risico_classificatie: 'B',
            start_evenement: now(),
            eind_evenement: now()->addDay(),
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test Event'
        ),
    ]);

    // No organiser notification
    Notification::assertNotSentTo(
        [$this->organiser],
        NewZaak::class
    );

    // Reviewer users should be notified
    Notification::assertSentTo(
        [$this->reviewer, $this->reviewerMunicipalityAdmin],
        NewZaak::class
    );
});

test('users who unsubscribed from notification should not receive it', function () {
    // Now disable the NewZaak notification for the reviewer
    NotificationPreference::create([
        'user_id' => $this->reviewer->id,
        'notification_class' => NewZaak::class,
        'channels' => [],
    ]);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'organiser_user_id' => $this->organiser->id,
        'reference_data' => new ZaakReferenceData(
            risico_classificatie: 'B',
            start_evenement: now(),
            eind_evenement: now()->addDay(),
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test Event'
        ),
    ]);

    // Reviewer should NOT be notified (unsubscribed)
    Notification::assertNotSentTo(
        [$this->reviewer],
        NewZaak::class
    );

    // Organiser and ReviewerMunicipalityAdmin should still be notified
    Notification::assertSentTo(
        [$this->organiser, $this->reviewerMunicipalityAdmin],
        NewZaak::class
    );
});

test('notification uses different texts and links based on user type', function () {
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'organiser_user_id' => $this->organiser->id,
        'reference_data' => new ZaakReferenceData(
            risico_classificatie: 'B',
            start_evenement: now(),
            eind_evenement: now()->addDay(),
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test Event'
        ),
    ]);

    $notification = new NewZaak($zaak);

    // Test organiser mail
    $organiserMail = $notification->toMail($this->organiser);
    expect($organiserMail->subject)->toBe('Nieuwe aanvraag voor "Test Event" ontvangen');
    expect($organiserMail->markdown)->toBe('mail.new-zaak');
    expect($organiserMail->viewData['type'])->toBe('organiser');
    expect($organiserMail->viewData['event'])->toBe('Test Event');
    expect($organiserMail->viewData['municipality'])->toBe('Test Municipality');
    expect($organiserMail->viewData['viewUrl'])->toContain(
        route('filament.organiser.resources.zaken.view', [
            'tenant' => $this->organisation->uuid,
            'record' => $zaak->id,
        ])
    );

    // Test organiser database notification
    $organiserDatabase = $notification->toDatabase($this->organiser);
    expect($organiserDatabase['title'])->toBe('Nieuwe aanvraag voor "Test Event" ontvangen');
    expect($organiserDatabase['body'])->toBe('Je nieuwe aanvraag voor "Test Event" bij Test Municipality is succesvol ontvangen.');

    // Test reviewer mail
    $reviewerMail = $notification->toMail($this->reviewer);
    expect($reviewerMail->subject)->toBe('Nieuw zaak "Test Event" beschikbaar');
    expect($reviewerMail->markdown)->toBe('mail.new-zaak');
    expect($reviewerMail->viewData['type'])->toBe('reviewer');
    expect($reviewerMail->viewData['event'])->toBe('Test Event');
    expect($reviewerMail->viewData['municipality'])->toBe('Test Municipality');
    expect($reviewerMail->viewData['viewUrl'])->toContain(
        route('filament.municipality.resources.zaken.view', [
            'tenant' => $this->municipality->id,
            'record' => $zaak->id,
        ])
    );

    // Test reviewer database notification
    $reviewerDatabase = $notification->toDatabase($this->reviewer);
    expect($reviewerDatabase['title'])->toBe('Nieuw zaak voor "Test Event"');
    expect($reviewerDatabase['body'])->toBe('Er is een nieuw zaak ontvangen voor "Test Event" bij Test Municipality.');
});
