<?php

use App\Jobs\CleanupExpiredInvites;
use App\Models\AdminInvite;
use App\Models\Advisory;
use App\Models\AdvisoryInvite;
use App\Models\MunicipalityInvite;
use App\Models\Organisation;
use App\Models\OrganisationInvite;

beforeEach(function () {
    // Set a known expiration period for testing
    config(['invites.expiration_days' => 7]);
});

test('deletes expired admin invites', function () {
    // Create expired invite (8 days old)
    $expiredInvite = AdminInvite::factory()->create([
        'created_at' => now()->subDays(8),
    ]);

    // Create fresh invite (3 days old)
    $freshInvite = AdminInvite::factory()->create([
        'created_at' => now()->subDays(3),
    ]);

    // Run the job
    CleanupExpiredInvites::dispatch();

    // Assert expired invite is deleted
    expect(AdminInvite::find($expiredInvite->id))->toBeNull();

    // Assert fresh invite still exists
    expect(AdminInvite::find($freshInvite->id))->not()->toBeNull();
});

test('deletes expired organisation invites', function () {
    $organisation = Organisation::factory()->create();

    // Create expired invite
    $expiredInvite = OrganisationInvite::factory()->create([
        'organisation_id' => $organisation->id,
        'created_at' => now()->subDays(10),
    ]);

    // Create fresh invite
    $freshInvite = OrganisationInvite::factory()->create([
        'organisation_id' => $organisation->id,
        'created_at' => now()->subDays(5),
    ]);

    CleanupExpiredInvites::dispatch();

    expect(OrganisationInvite::find($expiredInvite->id))->toBeNull();
    expect(OrganisationInvite::find($freshInvite->id))->not()->toBeNull();
});

test('deletes expired municipality invites', function () {
    // Create expired invite
    $expiredInvite = MunicipalityInvite::factory()->create([
        'created_at' => now()->subDays(14),
    ]);

    // Create fresh invite
    $freshInvite = MunicipalityInvite::factory()->create([
        'created_at' => now()->subDays(2),
    ]);

    CleanupExpiredInvites::dispatch();

    expect(MunicipalityInvite::find($expiredInvite->id))->toBeNull();
    expect(MunicipalityInvite::find($freshInvite->id))->not()->toBeNull();
});

test('deletes expired advisory invites', function () {
    $advisory = Advisory::factory()->create();

    // Create expired invite
    $expiredInvite = AdvisoryInvite::factory()->create([
        'advisory_id' => $advisory->id,
        'created_at' => now()->subDays(8),
    ]);

    // Create fresh invite
    $freshInvite = AdvisoryInvite::factory()->create([
        'advisory_id' => $advisory->id,
        'created_at' => now()->subDays(1),
    ]);

    CleanupExpiredInvites::dispatch();

    expect(AdvisoryInvite::find($expiredInvite->id))->toBeNull();
    expect(AdvisoryInvite::find($freshInvite->id))->not()->toBeNull();
});

test('handles multiple expired invites across different models', function () {
    $organisation = Organisation::factory()->create();
    $advisory = Advisory::factory()->create();

    // Create multiple expired invites
    $expiredAdmin = AdminInvite::factory()->create(['created_at' => now()->subDays(8)]);
    $expiredOrg = OrganisationInvite::factory()->create([
        'organisation_id' => $organisation->id,
        'created_at' => now()->subDays(9),
    ]);
    $expiredMunicipality = MunicipalityInvite::factory()->create(['created_at' => now()->subDays(10)]);
    $expiredAdvisory = AdvisoryInvite::factory()->create([
        'advisory_id' => $advisory->id,
        'created_at' => now()->subDays(11),
    ]);

    // Create fresh invites
    $freshAdmin = AdminInvite::factory()->create(['created_at' => now()->subDays(3)]);
    $freshOrg = OrganisationInvite::factory()->create([
        'organisation_id' => $organisation->id,
        'created_at' => now()->subDays(2),
    ]);

    CleanupExpiredInvites::dispatch();

    // Assert all expired invites are deleted
    expect(AdminInvite::find($expiredAdmin->id))->toBeNull();
    expect(OrganisationInvite::find($expiredOrg->id))->toBeNull();
    expect(MunicipalityInvite::find($expiredMunicipality->id))->toBeNull();
    expect(AdvisoryInvite::find($expiredAdvisory->id))->toBeNull();

    // Assert fresh invites still exist
    expect(AdminInvite::find($freshAdmin->id))->not()->toBeNull();
    expect(OrganisationInvite::find($freshOrg->id))->not()->toBeNull();
});

test('does not delete invites when none are expired', function () {
    $organisation = Organisation::factory()->create();

    // Create only fresh invites
    $adminInvite = AdminInvite::factory()->create(['created_at' => now()->subDays(3)]);
    $orgInvite = OrganisationInvite::factory()->create([
        'organisation_id' => $organisation->id,
        'created_at' => now()->subDays(2),
    ]);
    $municipalityInvite = MunicipalityInvite::factory()->create(['created_at' => now()->subDays(1)]);

    $initialCount = AdminInvite::count() + OrganisationInvite::count() + MunicipalityInvite::count();

    CleanupExpiredInvites::dispatch();

    $finalCount = AdminInvite::count() + OrganisationInvite::count() + MunicipalityInvite::count();

    expect($finalCount)->toBe($initialCount);
    expect(AdminInvite::find($adminInvite->id))->not()->toBeNull();
    expect(OrganisationInvite::find($orgInvite->id))->not()->toBeNull();
    expect(MunicipalityInvite::find($municipalityInvite->id))->not()->toBeNull();
});

test('respects custom expiration configuration', function () {
    // Set custom expiration period
    config(['invites.expiration_days' => 14]);

    // Create invite that would be expired with 7 days but not with 14 days
    $invite = AdminInvite::factory()->create([
        'created_at' => now()->subDays(10),
    ]);

    CleanupExpiredInvites::dispatch();

    // Should still exist because 10 days < 14 days
    expect(AdminInvite::find($invite->id))->not()->toBeNull();

    // Now make it truly expired
    $invite->created_at = now()->subDays(15);
    $invite->save();

    CleanupExpiredInvites::dispatch();

    // Now it should be deleted
    expect(AdminInvite::find($invite->id))->toBeNull();
});
