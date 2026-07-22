<?php

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\NotificationPreference;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\ZaakReleased;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->organisation = Organisation::factory()->create();

    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $this->zgwZaakUrl,
    ]);

    $this->makeUser = function (Role $role): User {
        $user = User::factory()->create(['role' => $role]);
        $user->municipalities()->attach($this->municipality);

        return $user;
    };

    $this->actAs = function (User $user) {
        $this->actingAs($user);
        Filament::setTenant($this->municipality);
    };
});

describe('claim zaak visibility', function () {
    it('shows the claim action to handling roles on an unassigned zaak', function (Role $role) {
        ($this->actAs)(($this->makeUser)($role));

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionVisible('claim_zaak');
    })->with([
        'reviewer' => Role::Reviewer,
        'coordinator' => Role::Coordinator,
        'reviewer municipality admin' => Role::ReviewerMunicipalityAdmin,
    ]);

    it('hides the claim action from a municipality admin', function () {
        ($this->actAs)(($this->makeUser)(Role::MunicipalityAdmin));

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionHidden('claim_zaak');
    });

    it('hides the claim action when the zaak is assigned to a colleague', function () {
        $colleague = ($this->makeUser)(Role::Reviewer);
        $this->zaak->update(['reviewer_user_id' => $colleague->id]);

        ($this->actAs)(($this->makeUser)(Role::Reviewer));

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionHidden('claim_zaak');
    });

    it('hides the claim action when the zaak is already assigned to the user themselves', function () {
        $reviewer = ($this->makeUser)(Role::Reviewer);
        $this->zaak->update(['reviewer_user_id' => $reviewer->id]);

        ($this->actAs)($reviewer);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionHidden('claim_zaak');
    });

    it('hides the claim action when the zaak has a resultaat', function () {
        $this->zaak->update([
            'reference_data' => new ZaakReferenceData(
                start_evenement: now(),
                eind_evenement: now()->addDay(),
                registratiedatum: now(),
                status_name: 'Afgerond',
                statustype_url: '',
                resultaat: 'Verleend',
            ),
        ]);

        ($this->actAs)(($this->makeUser)(Role::Reviewer));

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionHidden('claim_zaak');
    });

    it('hides the claim action on an imported zaak', function () {
        $importedZaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
            'zgw_zaak_url' => null,
            'imported_data' => ['bron' => 'import'],
        ]);

        ($this->actAs)(($this->makeUser)(Role::Reviewer));

        livewire(ViewZaak::class, ['record' => $importedZaak->id])
            ->assertOk()
            ->assertActionHidden('claim_zaak');
    });
});

describe('claim zaak behaviour', function () {
    it('assigns the zaak to the acting user without sending notifications', function () {
        Notification::fake();

        $reviewer = ($this->makeUser)(Role::Reviewer);
        ($this->actAs)($reviewer);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->callAction('claim_zaak')
            ->assertHasNoActionErrors()
            ->assertNotified();

        expect($this->zaak->refresh()->reviewer_user_id)->toBe($reviewer->id);
        Notification::assertNothingSent();
    });

    it('does not overwrite a colleague who claimed the zaak in the meantime', function () {
        $reviewer = ($this->makeUser)(Role::Reviewer);
        $colleague = ($this->makeUser)(Role::Reviewer);

        ($this->actAs)($reviewer);

        $page = livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk();

        // A colleague claims the zaak after the page was loaded but before the click.
        Zaak::whereKey($this->zaak->id)->update(['reviewer_user_id' => $colleague->id]);

        $page->callAction('claim_zaak');

        expect($this->zaak->refresh()->reviewer_user_id)->toBe($colleague->id);
    });
});

describe('release zaak visibility', function () {
    it('shows the release action only to the assigned handler themselves', function (Role $role) {
        $user = ($this->makeUser)($role);
        $this->zaak->update(['reviewer_user_id' => $user->id]);

        ($this->actAs)($user);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionVisible('release_zaak');
    })->with([
        'reviewer' => Role::Reviewer,
        'coordinator' => Role::Coordinator,
        'reviewer municipality admin' => Role::ReviewerMunicipalityAdmin,
    ]);

    it('hides the release action when the zaak is unassigned', function () {
        ($this->actAs)(($this->makeUser)(Role::Reviewer));

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionHidden('release_zaak');
    });

    it('hides the release action when the zaak is assigned to a colleague', function () {
        $colleague = ($this->makeUser)(Role::Reviewer);
        $this->zaak->update(['reviewer_user_id' => $colleague->id]);

        ($this->actAs)(($this->makeUser)(Role::Reviewer));

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionHidden('release_zaak');
    });

    it('hides the release action when the zaak has a resultaat', function () {
        $reviewer = ($this->makeUser)(Role::Reviewer);
        $this->zaak->update([
            'reviewer_user_id' => $reviewer->id,
            'reference_data' => new ZaakReferenceData(
                start_evenement: now(),
                eind_evenement: now()->addDay(),
                registratiedatum: now(),
                status_name: 'Afgerond',
                statustype_url: '',
                resultaat: 'Verleend',
            ),
        ]);

        ($this->actAs)($reviewer);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionHidden('release_zaak');
    });
});

describe('release zaak behaviour', function () {
    it('unassigns the zaak and notifies the coordinators, not the releasing user', function () {
        Notification::fake();

        $reviewer = ($this->makeUser)(Role::Reviewer);
        $coordinator = ($this->makeUser)(Role::Coordinator);
        $this->zaak->update(['reviewer_user_id' => $reviewer->id]);

        ($this->actAs)($reviewer);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->callAction('release_zaak')
            ->assertHasNoActionErrors()
            ->assertNotified();

        expect($this->zaak->refresh()->reviewer_user_id)->toBeNull();
        Notification::assertSentTo([$coordinator], ZaakReleased::class);
        Notification::assertNotSentTo([$reviewer], ZaakReleased::class);
    });

    it('falls back to notifying the other reviewers when the municipality has no coordinators', function () {
        Notification::fake();

        $reviewer = ($this->makeUser)(Role::Reviewer);
        $otherReviewer = ($this->makeUser)(Role::ReviewerMunicipalityAdmin);
        $this->zaak->update(['reviewer_user_id' => $reviewer->id]);

        ($this->actAs)($reviewer);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->callAction('release_zaak')
            ->assertHasNoActionErrors();

        expect($this->zaak->refresh()->reviewer_user_id)->toBeNull();
        Notification::assertSentTo([$otherReviewer], ZaakReleased::class);
        Notification::assertNotSentTo([$reviewer], ZaakReleased::class);
    });

    it('succeeds without notifications when the releasing user is the only handler', function () {
        Notification::fake();

        $reviewer = ($this->makeUser)(Role::Reviewer);
        $this->zaak->update(['reviewer_user_id' => $reviewer->id]);

        ($this->actAs)($reviewer);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->callAction('release_zaak')
            ->assertHasNoActionErrors()
            ->assertNotified();

        expect($this->zaak->refresh()->reviewer_user_id)->toBeNull();
        Notification::assertNothingSent();
    });

    it('respects a coordinator preference that disables the notification', function () {
        Notification::fake();

        $reviewer = ($this->makeUser)(Role::Reviewer);
        $coordinator = ($this->makeUser)(Role::Coordinator);
        $this->zaak->update(['reviewer_user_id' => $reviewer->id]);

        NotificationPreference::create([
            'user_id' => $coordinator->id,
            'notification_class' => ZaakReleased::class,
            'channels' => [],
        ]);

        ($this->actAs)($reviewer);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->callAction('release_zaak')
            ->assertHasNoActionErrors();

        Notification::assertNotSentTo([$coordinator], ZaakReleased::class);
    });
});

describe('ZaakReleased notification', function () {
    beforeEach(function () {
        $this->zaak->update([
            'reference_data' => new ZaakReferenceData(
                start_evenement: now(),
                eind_evenement: now()->addDay(),
                registratiedatum: now(),
                status_name: 'Ontvangen',
                statustype_url: '',
                risico_classificatie: 'A',
                naam_locatie_eveneme: 'Test locatie',
                naam_evenement: 'Test Event',
            ),
        ]);

        $this->releasedBy = User::factory()->create([
            'role' => Role::Reviewer,
            'first_name' => 'Test',
            'last_name' => 'Behandelaar',
        ]);
    });

    it('generates correct mail content', function () {
        $coordinator = ($this->makeUser)(Role::Coordinator);
        $notification = new ZaakReleased($this->zaak->refresh(), $this->releasedBy);
        $mail = $notification->toMail($coordinator);

        expect($mail->subject)->toBe('Zaak "Test Event" is vrijgegeven');
        expect($mail->markdown)->toBe('mail.zaak-released');
        expect($mail->viewData['event'])->toBe('Test Event');
        expect($mail->viewData['municipality'])->toBe('Test Municipality');
        expect($mail->viewData['releasedBy'])->toBe($this->releasedBy->name);
        expect($mail->viewData['viewUrl'])->toContain(
            route('filament.municipality.resources.zaken.view', [
                'tenant' => $this->municipality->id,
                'record' => $this->zaak->id,
            ])
        );
    });

    it('generates correct database notification content', function () {
        $coordinator = ($this->makeUser)(Role::Coordinator);
        $notification = new ZaakReleased($this->zaak->refresh(), $this->releasedBy);
        $database = $notification->toDatabase($coordinator);

        expect($database['title'])->toBe($this->releasedBy->name.' heeft zaak "Test Event" vrijgegeven');
    });
});
