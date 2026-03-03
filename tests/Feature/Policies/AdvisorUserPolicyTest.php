<?php

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\User;
use App\Policies\AdvisorUserPolicy;

beforeEach(function () {
    $this->policy = new AdvisorUserPolicy;
});

describe('viewAny', function () {
    it('allows admin to view any advisor users', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);

        expect($this->policy->viewAny($admin))->toBeTrue();
    });

    it('allows advisor to view any advisor users', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->viewAny($advisor))->toBeTrue();
    });

    it('allows municipality admin to view any advisor users', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);

        expect($this->policy->viewAny($municipalityAdmin))->toBeTrue();
    });

    it('allows reviewer municipality admin to view any advisor users', function () {
        $reviewerMunicipalityAdmin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);

        expect($this->policy->viewAny($reviewerMunicipalityAdmin))->toBeTrue();
    });

    it('denies organiser to view any advisor users', function () {
        $organiser = User::factory()->create(['role' => Role::Organiser]);

        expect($this->policy->viewAny($organiser))->toBeFalse();
    });
});

describe('view', function () {
    it('allows admin to view specific advisor user', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->view($admin, $advisor))->toBeTrue();
    });

    it('allows municipality admin to view specific advisor user', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->view($municipalityAdmin, $advisor))->toBeTrue();
    });

    it('denies organiser to view specific advisor user', function () {
        $organiser = User::factory()->create(['role' => Role::Organiser]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->view($organiser, $advisor))->toBeFalse();
    });
});

describe('create', function () {
    it('denies all users from creating advisor users', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);

        expect($this->policy->create($admin))->toBeFalse();
    });
});

describe('update', function () {
    it('allows user to update their own profile', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->update($advisor, $advisor))->toBeTrue();
    });

    it('allows admin to update any advisor user', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->update($admin, $advisor))->toBeTrue();
    });

    it('allows municipality admin to update advisor in their single advisory', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipality = Municipality::factory()->create();
        $municipalityAdmin->municipalities()->attach($municipality);

        $advisory = Advisory::factory()->create();
        $advisory->municipalities()->attach($municipality);

        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

        expect($this->policy->update($municipalityAdmin, $advisor))->toBeTrue();
    });

    it('denies municipality admin to update advisor in multiple advisories', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipality = Municipality::factory()->create();
        $municipalityAdmin->municipalities()->attach($municipality);

        $advisory1 = Advisory::factory()->create();
        $advisory2 = Advisory::factory()->create();

        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->advisories()->attach($advisory1, ['role' => AdvisoryRole::Member]);
        $advisor->advisories()->attach($advisory2, ['role' => AdvisoryRole::Member]);

        expect($this->policy->update($municipalityAdmin, $advisor))->toBeFalse();
    });

    it('allows advisory admin to update advisor in same advisory', function () {
        $advisoryAdmin = User::factory()->create(['role' => Role::Advisor]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        $advisory = Advisory::factory()->create();
        $advisoryAdmin->advisories()->attach($advisory, ['role' => AdvisoryRole::Admin]);
        $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

        expect($this->policy->update($advisoryAdmin, $advisor))->toBeTrue();
    });

    it('denies advisory member to update another advisor', function () {
        $advisoryMember = User::factory()->create(['role' => Role::Advisor]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        $advisory = Advisory::factory()->create();
        $advisoryMember->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);
        $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

        expect($this->policy->update($advisoryMember, $advisor))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows user to delete their own profile', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->delete($advisor, $advisor))->toBeTrue();
    });

    it('denies soft-deleted user to delete any profile', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $anotherAdvisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->delete();

        expect($this->policy->delete($advisor, $anotherAdvisor))->toBeFalse();
    });

    it('allows admin to delete any advisor user', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->delete($admin, $advisor))->toBeTrue();
    });

    it('allows municipality admin to delete advisor with single advisory in their municipality', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipality = Municipality::factory()->create();
        $municipalityAdmin->municipalities()->attach($municipality);

        $advisory = Advisory::factory()->create();
        $advisory->municipalities()->attach($municipality);

        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

        // This tests line 96 - checking the advisory's municipality matches user's municipality
        expect($this->policy->delete($municipalityAdmin, $advisor))->toBeTrue();
    });

    it('denies municipality admin to delete advisor in different municipality', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipality1 = Municipality::factory()->create();
        $municipality2 = Municipality::factory()->create();
        $municipalityAdmin->municipalities()->attach($municipality1);

        $advisory = Advisory::factory()->create();
        $advisory->municipalities()->attach($municipality2);

        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

        expect($this->policy->delete($municipalityAdmin, $advisor))->toBeFalse();
    });

    it('denies municipality admin to delete advisor in multiple advisories', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $municipality = Municipality::factory()->create();
        $municipalityAdmin->municipalities()->attach($municipality);

        $advisory1 = Advisory::factory()->create();
        $advisory2 = Advisory::factory()->create();
        $advisory1->municipalities()->attach($municipality);
        $advisory2->municipalities()->attach($municipality);

        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->advisories()->attach($advisory1, ['role' => AdvisoryRole::Member]);
        $advisor->advisories()->attach($advisory2, ['role' => AdvisoryRole::Member]);

        expect($this->policy->delete($municipalityAdmin, $advisor))->toBeFalse();
    });

    it('allows advisory admin to delete advisor in same advisory when advisor has only one advisory', function () {
        $advisoryAdmin = User::factory()->create(['role' => Role::Advisor]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        $advisory = Advisory::factory()->create();
        $advisoryAdmin->advisories()->attach($advisory, ['role' => AdvisoryRole::Admin]);
        $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

        expect($this->policy->delete($advisoryAdmin, $advisor))->toBeTrue();
    });

    it('denies advisory admin to delete advisor in same advisory when advisor has multiple advisories', function () {
        $advisoryAdmin = User::factory()->create(['role' => Role::Advisor]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        $advisory1 = Advisory::factory()->create();
        $advisory2 = Advisory::factory()->create();
        $advisoryAdmin->advisories()->attach($advisory1, ['role' => AdvisoryRole::Admin]);
        $advisor->advisories()->attach($advisory1, ['role' => AdvisoryRole::Member]);
        $advisor->advisories()->attach($advisory2, ['role' => AdvisoryRole::Member]);

        expect($this->policy->delete($advisoryAdmin, $advisor))->toBeFalse();
    });

    it('denies advisory admin to delete advisor with no shared advisories', function () {
        $advisoryAdmin = User::factory()->create(['role' => Role::Advisor]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        $advisory = Advisory::factory()->create();
        $advisoryAdmin->advisories()->attach($advisory, ['role' => AdvisoryRole::Admin]);

        // The advisory admin is not admin for any advisory that the advisor belongs to
        // so they cannot delete the advisor
        expect($this->policy->delete($advisoryAdmin, $advisor))->toBeFalse();
    });

    it('denies advisory member to delete another advisor', function () {
        $advisoryMember = User::factory()->create(['role' => Role::Advisor]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        $advisory = Advisory::factory()->create();
        $advisoryMember->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);
        $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

        expect($this->policy->delete($advisoryMember, $advisor))->toBeFalse();
    });
});

describe('restore', function () {
    it('denies soft-deleted user to restore any profile', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $anotherAdvisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->delete();

        expect($this->policy->restore($advisor, $anotherAdvisor))->toBeFalse();
    });

    it('denies all users from restoring advisor users', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->restore($admin, $advisor))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies soft-deleted user to force delete any profile', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);
        $anotherAdvisor = User::factory()->create(['role' => Role::Advisor]);
        $advisor->delete();

        expect($this->policy->forceDelete($advisor, $anotherAdvisor))->toBeFalse();
    });

    it('denies all users from force deleting advisor users', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->forceDelete($admin, $advisor))->toBeFalse();
    });
});
