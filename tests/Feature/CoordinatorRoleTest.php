<?php

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\CoordinatorUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\AssignedToZaak;
use App\Notifications\NewZaak;
use App\Policies\CoordinatorUserPolicy;
use App\Policies\ZaakPolicy;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);
    $this->otherMunicipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->organisation = Organisation::factory()->create();
});

// --- CoordinatorUserPolicy ---

describe('CoordinatorUserPolicy', function () {
    beforeEach(function () {
        $this->policy = new CoordinatorUserPolicy;
        $this->coordinatorUser = User::factory()->create(['role' => Role::Coordinator]);
        $this->coordinatorUser->municipalities()->attach($this->municipality);
    });

    it('allows anyone to viewAny', function () {
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        expect($this->policy->viewAny($reviewer))->toBeTrue();
    });

    it('allows admin to view coordinator user', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        expect($this->policy->view($admin, CoordinatorUser::find($this->coordinatorUser->id)))->toBeTrue();
    });

    it('denies non-admin from viewing coordinator user', function () {
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        expect($this->policy->view($reviewer, CoordinatorUser::find($this->coordinatorUser->id)))->toBeFalse();
    });

    it('denies create for all users', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        expect($this->policy->create($admin))->toBeFalse();
    });

    it('allows admin to update coordinator user', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        expect($this->policy->update($admin, CoordinatorUser::find($this->coordinatorUser->id)))->toBeTrue();
    });

    it('allows municipality admin in same municipality to update coordinator user', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->municipality);

        expect($this->policy->update($municipalityAdmin, CoordinatorUser::find($this->coordinatorUser->id)))->toBeTrue();
    });

    it('denies municipality admin in different municipality from updating coordinator user', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->otherMunicipality);

        expect($this->policy->update($municipalityAdmin, CoordinatorUser::find($this->coordinatorUser->id)))->toBeFalse();
    });

    it('allows reviewer municipality admin in same municipality to update coordinator user', function () {
        $reviewerAdmin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
        $reviewerAdmin->municipalities()->attach($this->municipality);

        expect($this->policy->update($reviewerAdmin, CoordinatorUser::find($this->coordinatorUser->id)))->toBeTrue();
    });

    it('allows admin to delete coordinator user', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        expect($this->policy->delete($admin, CoordinatorUser::find($this->coordinatorUser->id)))->toBeTrue();
    });

    it('allows municipality admin in same municipality to delete coordinator user', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->municipality);

        expect($this->policy->delete($municipalityAdmin, CoordinatorUser::find($this->coordinatorUser->id)))->toBeTrue();
    });

    it('denies municipality admin in different municipality from deleting coordinator user', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->otherMunicipality);

        expect($this->policy->delete($municipalityAdmin, CoordinatorUser::find($this->coordinatorUser->id)))->toBeFalse();
    });

    it('denies reviewer from deleting coordinator user', function () {
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        expect($this->policy->delete($reviewer, CoordinatorUser::find($this->coordinatorUser->id)))->toBeFalse();
    });
});

// --- ZaakPolicy for coordinator ---

describe('ZaakPolicy for coordinator', function () {
    beforeEach(function () {
        $this->policy = new ZaakPolicy;
        $this->zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
        ]);
    });

    it('allows coordinator to viewAny', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        expect($this->policy->viewAny($coordinator))->toBeTrue();
    });

    it('allows coordinator in same municipality to view zaak', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        expect($this->policy->view($coordinator, $this->zaak))->toBeTrue();
    });

    it('denies coordinator in different municipality from viewing zaak', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->otherMunicipality);

        expect($this->policy->view($coordinator, $this->zaak))->toBeFalse();
    });

    it('allows coordinator in same municipality to upload a document', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        expect($this->policy->uploadDocument($coordinator, $this->zaak))->toBeTrue();
    });

    it('denies coordinator in different municipality from uploading a document', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->otherMunicipality);

        expect($this->policy->uploadDocument($coordinator, $this->zaak))->toBeFalse();
    });

    it('allows coordinator in same municipality to view the activity log', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        expect($this->policy->viewActivity($coordinator, $this->zaak))->toBeTrue();
    });

    it('denies coordinator in different municipality from viewing the activity log', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->otherMunicipality);

        expect($this->policy->viewActivity($coordinator, $this->zaak))->toBeFalse();
    });
});

// --- Coordinator can finish a zaak ---

describe('coordinator can finish a zaak', function () {
    beforeEach(function () {
        Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
        Filament::setCurrentPanel(Filament::getPanel('municipality'));

        $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
        ZgwHttpFake::wildcardFake();

        $this->zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
            'zgw_zaak_url' => $zgwZaakUrl,
        ]);
    });

    it('shows the finish zaak action to a coordinator', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        $this->actingAs($coordinator);
        Filament::setTenant($this->municipality);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionVisible('finish_zaak');
    });

    it('hides the finish zaak action from a municipality admin', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->municipality);

        $this->actingAs($municipalityAdmin);
        Filament::setTenant($this->municipality);

        livewire(ViewZaak::class, ['record' => $this->zaak->id])
            ->assertOk()
            ->assertActionHidden('finish_zaak');
    });
});

// --- getMunicipalityHandlers ---

describe('getMunicipalityHandlers', function () {
    it('returns assigned reviewer when reviewer_user_id is set', function () {
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $reviewer->municipalities()->attach($this->municipality);

        $zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
            'reviewer_user_id' => $reviewer->id,
        ]);

        $handlers = $zaak->getMunicipalityHandlers();

        expect($handlers)->toHaveCount(1);
        expect($handlers[0]->id)->toBe($reviewer->id);
    });

    it('returns coordinators when no reviewer assigned and coordinators exist', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $reviewer->municipalities()->attach($this->municipality);

        $zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
        ]);

        $handlers = $zaak->getMunicipalityHandlers();

        expect($handlers)->toHaveCount(1);
        expect($handlers[0]->id)->toBe($coordinator->id);
    });

    it('falls back to all reviewers when no reviewer assigned and no coordinators exist', function () {
        $reviewer1 = User::factory()->create(['role' => Role::Reviewer]);
        $reviewer1->municipalities()->attach($this->municipality);

        $reviewer2 = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
        $reviewer2->municipalities()->attach($this->municipality);

        $zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
        ]);

        $handlers = $zaak->getMunicipalityHandlers();

        expect($handlers)->toHaveCount(2);
        expect(collect($handlers)->pluck('id')->sort()->values()->all())
            ->toBe(collect([$reviewer1->id, $reviewer2->id])->sort()->values()->all());
    });

    it('returns empty array when municipality is null', function () {
        Zaak::withoutEvents(function () {
            $zaak = Zaak::factory()->create([
                'organisation_id' => $this->organisation->id,
            ]);

            $handlers = $zaak->getMunicipalityHandlers();

            expect($handlers)->toBe([]);
        });
    });
});

// --- NewZaak notification with coordinators ---

describe('NewZaak notification respects coordinator priority', function () {
    it('notifies coordinators instead of reviewers when coordinators exist', function () {
        Notification::fake();

        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $reviewer->municipalities()->attach($this->municipality);

        Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
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

        Notification::assertSentTo([$coordinator], NewZaak::class);
        Notification::assertNotSentTo([$reviewer], NewZaak::class);
    });

    it('notifies all reviewers when no coordinators exist', function () {
        Notification::fake();

        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $reviewer->municipalities()->attach($this->municipality);

        $reviewerAdmin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
        $reviewerAdmin->municipalities()->attach($this->municipality);

        Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
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

        Notification::assertSentTo([$reviewer, $reviewerAdmin], NewZaak::class);
    });
});

// --- AssignedToZaak notification ---

describe('AssignedToZaak notification', function () {
    it('generates correct mail content', function () {
        $zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
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

        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $notification = new AssignedToZaak($zaak);
        $mail = $notification->toMail($reviewer);

        expect($mail->subject)->toBe('Zaak "Test Event" aan u toegewezen');
        expect($mail->markdown)->toBe('mail.assigned-to-zaak');
        expect($mail->viewData['event'])->toBe('Test Event');
        expect($mail->viewData['municipality'])->toBe('Test Municipality');
        expect($mail->viewData['viewUrl'])->toContain(
            route('filament.municipality.resources.zaken.view', [
                'tenant' => $this->municipality->id,
                'record' => $zaak->id,
            ])
        );
    });

    it('generates correct database notification content', function () {
        $zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
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

        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $notification = new AssignedToZaak($zaak);
        $database = $notification->toDatabase($reviewer);

        expect($database['title'])->toBe('Zaak "Test Event" aan u toegewezen');
    });
});

// --- CoordinatorUser model ---

describe('CoordinatorUser model', function () {
    it('resolves to CoordinatorUser class for coordinator role', function () {
        expect(User::resolveClassForRole(Role::Coordinator))->toBe(CoordinatorUser::class);
    });

    it('has correct role scope', function () {
        User::factory()->create(['role' => Role::Coordinator]);
        User::factory()->create(['role' => Role::Reviewer]);

        expect(CoordinatorUser::count())->toBe(1);
    });
});

// --- Coordinator assignable as reviewer ---

describe('coordinator assignable as reviewer', function () {
    it('includes coordinators in the municipality reviewer users', function () {
        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        $reviewer = User::factory()->create(['role' => Role::Reviewer]);
        $reviewer->municipalities()->attach($this->municipality);

        $reviewerAdmin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
        $reviewerAdmin->municipalities()->attach($this->municipality);

        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->municipality);

        $ids = $this->municipality->allReviewerUsers()->get()->pluck('id');

        expect($ids)->toContain($coordinator->id)
            ->toContain($reviewer->id)
            ->toContain($reviewerAdmin->id)
            ->not->toContain($municipalityAdmin->id);
    });

    it('lets a coordinator assign themselves as reviewer on a zaak', function () {
        Notification::fake();
        Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
        Filament::setCurrentPanel(Filament::getPanel('municipality'));

        $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
        ZgwHttpFake::wildcardFake();

        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        $zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
            'zgw_zaak_url' => $zgwZaakUrl,
        ]);

        $this->actingAs($coordinator);
        Filament::setTenant($this->municipality);

        livewire(ViewZaak::class, ['record' => $zaak->id])
            ->assertOk()
            ->callAction('assign_reviewer', data: [
                'reviewer_user_id' => $coordinator->id,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        expect($zaak->refresh()->reviewer_user_id)->toBe($coordinator->id);
        Notification::assertSentTo([$coordinator], AssignedToZaak::class);
    });

    it('does not offer a municipality admin as assignable reviewer', function () {
        Notification::fake();
        Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
        Filament::setCurrentPanel(Filament::getPanel('municipality'));

        $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
        ZgwHttpFake::wildcardFake();

        $coordinator = User::factory()->create(['role' => Role::Coordinator]);
        $coordinator->municipalities()->attach($this->municipality);

        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipalityAdmin->municipalities()->attach($this->municipality);

        $zaak = Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'organisation_id' => $this->organisation->id,
            'zgw_zaak_url' => $zgwZaakUrl,
        ]);

        $this->actingAs($coordinator);
        Filament::setTenant($this->municipality);

        livewire(ViewZaak::class, ['record' => $zaak->id])
            ->assertOk()
            ->callAction('assign_reviewer', data: [
                'reviewer_user_id' => $municipalityAdmin->id,
            ])
            ->assertHasActionErrors(['reviewer_user_id']);

        expect($zaak->refresh()->reviewer_user_id)->toBeNull();
    });
});
