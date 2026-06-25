<?php

declare(strict_types=1);

use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Support\Documents\DocumentVersionAuthorizer;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
    ]);
});

/**
 * Log a 'created' document activity attributing $creator as the author of the
 * first version of the given document uuid.
 */
function logDocumentCreatedBy(User $creator, Zaak $zaak, string $documentUuid): void
{
    activity('document')
        ->event('created')
        ->causedBy($creator)
        ->performedOn($zaak)
        ->withProperties(['document_uuid' => $documentUuid])
        ->log('created');
}

test('admin can always add a new version', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    expect(DocumentVersionAuthorizer::canAddVersion($admin, $this->zaak, 'doc-uuid'))->toBeTrue();
});

test('organiser is blocked when the original creator is unknown', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin->value]);

    expect(DocumentVersionAuthorizer::canAddVersion($organiser, $this->zaak, 'doc-uuid'))->toBeFalse();
});

test('advisor is blocked when the original creator is unknown', function () {
    $advisor = User::factory()->create(['role' => Role::Advisor]);

    expect(DocumentVersionAuthorizer::canAddVersion($advisor, $this->zaak, 'doc-uuid'))->toBeFalse();
});

test('municipality user can add a new version when the original creator is unknown', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);

    expect(DocumentVersionAuthorizer::canAddVersion($reviewer, $this->zaak, 'doc-uuid'))->toBeTrue();
});

test('organiser can add a new version of a document created by an organiser', function () {
    $creator = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($creator, ['role' => OrganisationRole::Admin->value]);
    logDocumentCreatedBy($creator, $this->zaak, 'doc-uuid');

    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin->value]);

    expect(DocumentVersionAuthorizer::canAddVersion($organiser, $this->zaak, 'doc-uuid'))->toBeTrue();
});

test('organiser cannot add a new version of a document created by a reviewer', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    logDocumentCreatedBy($reviewer, $this->zaak, 'doc-uuid');

    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin->value]);

    expect(DocumentVersionAuthorizer::canAddVersion($organiser, $this->zaak, 'doc-uuid'))->toBeFalse();
});

test('reviewer can add a new version of a document created by a reviewer', function () {
    $creator = User::factory()->create(['role' => Role::Reviewer]);
    logDocumentCreatedBy($creator, $this->zaak, 'doc-uuid');

    $reviewer = User::factory()->create(['role' => Role::Reviewer]);

    expect(DocumentVersionAuthorizer::canAddVersion($reviewer, $this->zaak, 'doc-uuid'))->toBeTrue();
});

test('reviewer cannot add a new version of a document created by an organiser', function () {
    $creator = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($creator, ['role' => OrganisationRole::Admin->value]);
    logDocumentCreatedBy($creator, $this->zaak, 'doc-uuid');

    $reviewer = User::factory()->create(['role' => Role::Reviewer]);

    expect(DocumentVersionAuthorizer::canAddVersion($reviewer, $this->zaak, 'doc-uuid'))->toBeFalse();
});

test('advisor can add a new version when sharing the same advisory as the creator', function () {
    $advisory = Advisory::factory()->create();

    $creator = User::factory()->create(['role' => Role::Advisor]);
    $creator->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);
    logDocumentCreatedBy($creator, $this->zaak, 'doc-uuid');

    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

    expect(DocumentVersionAuthorizer::canAddVersion($advisor, $this->zaak, 'doc-uuid'))->toBeTrue();
});

test('advisor cannot add a new version of a document created by a different advisory', function () {
    $advisory = Advisory::factory()->create();
    $otherAdvisory = Advisory::factory()->create();

    $creator = User::factory()->create(['role' => Role::Advisor]);
    $creator->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);
    logDocumentCreatedBy($creator, $this->zaak, 'doc-uuid');

    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($otherAdvisory, ['role' => AdvisoryRole::Member]);

    expect(DocumentVersionAuthorizer::canAddVersion($advisor, $this->zaak, 'doc-uuid'))->toBeFalse();
});
