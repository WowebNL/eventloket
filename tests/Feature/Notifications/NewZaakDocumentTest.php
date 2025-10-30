<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Thread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\NewZaakDocument;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->organiser = User::factory()->create([
        'email' => 'organiser@example.com',
        'role' => Role::Organiser,
    ]);

    $this->organisation = Organisation::factory()->create([
        'type' => 'business',
        'name' => 'Test organisation',
    ]);

    $this->organisation->users()->attach($this->organiser, [
        'role' => OrganisationRole::Admin,
    ]);

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->reviewer = User::factory()
        ->create([
            'role' => Role::Reviewer,
        ]);

    $this->municipality->users()->attach($this->reviewer);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    $this->advisory = Advisory::factory()->create([
        'name' => 'Brandweer',
    ]);

    $this->advisor = User::factory()->create([
        'role' => Role::Advisor,
    ]);
    $this->advisory->users()->attach($this->advisor, ['role' => 'member']);

    Thread::factory()->create([
        'title' => 'Advice Thread',
        'zaak_id' => $this->zaak->id,
        'advisory_id' => $this->advisory->id,
        'created_by' => $this->reviewer->id,
        'type' => ThreadType::Advice,
    ]);
});

test('Notification markdown mail rendered correctly', function () {
    $notification = new NewZaakDocument($this->zaak, 'document.pdf', true);

    $databseMessage = $notification->toDatabase($this->organiser);
    $this->assertIsArray($databseMessage);
    $this->assertArrayHasKey('actions', $databseMessage);

    $mailMessage = $notification->toMail($this->organiser);
    $mailMessage->render(); // acturally render the mail for testing if this works

    $viewData = $mailMessage->viewData;
    $markdown = $mailMessage->markdown;

    $this->assertEquals('mail.new-zaak-document', $markdown);
    $this->assertEquals('new', $viewData['type']);
    $this->assertEquals($this->zaak->reference_data->naam_evenement, $viewData['event']);
    $this->assertEquals('document.pdf', $viewData['filename']);
    $this->assertStringContainsString(
        route('filament.organiser.resources.zaken.view', [
            'tenant' => $this->organisation->uuid,
            'record' => $this->zaak->id,
        ]),
        $viewData['viewUrl']
    );

    $mailMessageForReviewer = $notification->toMail($this->reviewer);
    $viewDataForReviewer = $mailMessageForReviewer->viewData;

    $this->assertStringContainsString(
        route('filament.organiser.resources.zaken.view', [
            'tenant' => $this->municipality->id,
            'record' => $this->zaak->id,
        ]),
        $viewDataForReviewer['viewUrl']
    );

    $mailMessageForAdvisor = $notification->toMail($this->advisor);
    $viewDataForAdvisor = $mailMessageForAdvisor->viewData;

    $this->assertStringContainsString(
        route('filament.organiser.resources.zaken.view', [
            'tenant' => $this->zaak->adviceThreads->map(fn ($thread) => in_array($thread->advisory_id, $this->advisor->advisories->pluck('id')->toArray()))->first(),
            'record' => $this->zaak->id,
        ]),
        $viewDataForAdvisor['viewUrl']
    );

});

test('Organisation user receives notification for new zaak document', function () {
    Notification::fake();

    $this->zaak->organisation->users()->each(function (User $user) {
        $user->notify(new NewZaakDocument($this->zaak, 'document.pdf', 'new'));
    });

    Notification::assertSentTo(
        [$this->organiser],
        NewZaakDocument::class,
        function (NewZaakDocument $notification, $channels) {
            return in_array('mail', $channels) && in_array('database', $channels);
        }
    );
});

test('Municipality user receives notification for new zaak document', function () {
    Notification::fake();

    $this->zaak->municipality->users()->each(function (User $user) {
        $user->notify(new NewZaakDocument($this->zaak, 'document.pdf', 'new'));
    });

    Notification::assertSentTo(
        [$this->reviewer],
        NewZaakDocument::class,
        function (NewZaakDocument $notification, $channels) {
            return in_array('mail', $channels) && in_array('database', $channels);
        }
    );
});

test('Advisory user receives notification for new zaak document', function () {
    Notification::fake();

    $this->zaak->adviceThreads->first()->advisory->users()->each(function (User $user) {
        $user->notify(new NewZaakDocument($this->zaak, 'document.pdf', 'new'));
    });

    Notification::assertSentTo(
        [$this->advisor],
        NewZaakDocument::class,
        function (NewZaakDocument $notification, $channels) {
            return in_array('mail', $channels) && in_array('database', $channels);
        }
    );
});
