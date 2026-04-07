<?php

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Shared\Resources\Zaken\Filters\AdvisorWorkingstockFilter;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();

    $this->organisation = Organisation::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->advisory = Advisory::factory()->create([
        'name' => 'Test Advisory',
    ]);

    $this->advisory->municipalities()->attach($this->municipality);

    $this->advisor = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisor2 = User::factory()->create([
        'email' => 'advisor2@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisory->users()->attach($this->advisor, ['role' => AdvisoryRole::Admin]);
    $this->advisory->users()->attach($this->advisor2, ['role' => AdvisoryRole::Member]);
});

test('filter can be created', function () {
    $filter = AdvisorWorkingstockFilter::make();

    expect($filter)->toBeInstanceOf(Filter::class);
});

test('filter has correct default value', function () {
    $filter = AdvisorWorkingstockFilter::make();

    // The default should be 'new'
    $schema = $filter->getFormSchema();
    $toggleButtons = $schema[0];

    expect($toggleButtons->getDefaultState())->toBe('new');
});

test('filter has correct options', function () {
    $filter = AdvisorWorkingstockFilter::make();

    $schema = $filter->getFormSchema();
    $toggleButtons = $schema[0];
    $options = $toggleButtons->getOptions();

    expect($options)->toHaveCount(3)
        ->and($options)->toHaveKeys(['new', 'me', 'all'])
        ->and($options['new'])->toBe(__('resources/zaak.filters.workingstock.options.new'))
        ->and($options['me'])->toBe(__('resources/zaak.filters.workingstock.options.me'))
        ->and($options['all'])->toBe(__('resources/zaak.filters.workingstock.options.all'));
});

test('filter with new option shows zaken with unassigned active advice threads for the current tenant', function () {
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    // Create zaak with unassigned active advice thread — should be included
    $zaakWithUnassigned = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Test Zaak'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $zaakWithUnassigned->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Asked,
        'title' => 'Test Advice Thread',
        'created_by' => $this->advisor->id,
    ]);

    // Create zaak with assigned active thread — should NOT be included
    $zaakWithAssigned = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Test Zaak 2'
        ),
    ]);

    $assignedThread = AdviceThread::forceCreate([
        'zaak_id' => $zaakWithAssigned->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Asked,
        'title' => 'Test Advice Thread Assigned',
        'created_by' => $this->advisor->id,
    ]);
    $assignedThread->assignedUsers()->attach($this->advisor);

    // Create zaak with unassigned concept thread — should NOT be included
    $zaakWithConcept = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Concept Zaak'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $zaakWithConcept->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Concept,
        'title' => 'Concept Thread',
        'created_by' => $this->advisor->id,
    ]);

    // Create zaak with unassigned closed thread — should NOT be included
    $zaakWithClosed = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Closed Zaak'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $zaakWithClosed->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Approved,
        'title' => 'Closed Thread',
        'created_by' => $this->advisor->id,
    ]);

    // Apply filter with 'new' option
    $query = Zaak::query();
    $filter = AdvisorWorkingstockFilter::make();
    $filteredQuery = $filter->apply($query, ['workingstock-adv' => 'new']);

    $results = $filteredQuery->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($zaakWithUnassigned->id);
});

test('filter with me option shows zaken with active advice threads assigned to current user', function () {
    $this->actingAs($this->advisor);

    // Create zaak with active thread assigned to current user — should be included
    $myZaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'My Zaak'
        ),
    ]);

    $myThread = AdviceThread::forceCreate([
        'zaak_id' => $myZaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::InProgress,
        'title' => 'My Active Thread',
        'created_by' => $this->advisor->id,
    ]);
    $myThread->assignedUsers()->attach($this->advisor);

    // Create zaak with closed thread assigned to current user — should NOT be included
    $myClosedZaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'My Closed Zaak'
        ),
    ]);

    $myClosedThread = AdviceThread::forceCreate([
        'zaak_id' => $myClosedZaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Approved,
        'title' => 'My Closed Thread',
        'created_by' => $this->advisor->id,
    ]);
    $myClosedThread->assignedUsers()->attach($this->advisor);

    // Create zaak with active thread assigned to another user — should NOT be included
    $otherZaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Other Zaak'
        ),
    ]);

    $otherThread = AdviceThread::forceCreate([
        'zaak_id' => $otherZaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Asked,
        'title' => 'Other Advice Thread',
        'created_by' => $this->advisor2->id,
    ]);
    $otherThread->assignedUsers()->attach($this->advisor2);

    // Apply filter with 'me' option
    $query = Zaak::query();
    $filter = AdvisorWorkingstockFilter::make();
    $filteredQuery = $filter->apply($query, ['workingstock-adv' => 'me']);

    $results = $filteredQuery->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($myZaak->id);
});

test('filter with all option shows zaken with active and closed advice threads but not concept', function () {
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    // Active unassigned thread — should be included
    $zaakActive = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Active Zaak'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $zaakActive->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Asked,
        'title' => 'Active Thread',
        'created_by' => $this->advisor->id,
    ]);

    // Closed thread — should also be included by 'all'
    $zaakClosed = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Closed Zaak'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $zaakClosed->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Approved,
        'title' => 'Closed Thread',
        'created_by' => $this->advisor->id,
    ]);

    // Concept thread — should NOT be included by 'all'
    $zaakConcept = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Concept Zaak'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $zaakConcept->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Concept,
        'title' => 'Concept Thread',
        'created_by' => $this->advisor->id,
    ]);

    // Apply filter with 'all' option
    $query = Zaak::query();
    $filter = AdvisorWorkingstockFilter::make();
    $filteredQuery = $filter->apply($query, ['workingstock-adv' => 'all']);

    $results = $filteredQuery->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id'))->toContain($zaakActive->id)
        ->and($results->pluck('id'))->toContain($zaakClosed->id)
        ->and($results->pluck('id'))->not->toContain($zaakConcept->id);
});

test('filter query function works correctly with builder', function () {
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Test Zaak'
        ),
    ]);

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'title' => 'Test Thread',
        'created_by' => $this->advisor->id,
    ]);

    $filter = AdvisorWorkingstockFilter::make();
    $query = Zaak::query();

    // Test that the query method returns a Builder instance
    $filteredQuery = $filter->apply($query, ['workingstock-adv' => 'new']);

    expect($filteredQuery)->toBeInstanceOf(Builder::class);
});

test('filter handles empty data gracefully', function () {
    $filter = AdvisorWorkingstockFilter::make();
    $query = Zaak::query();

    // Apply filter with empty data
    $filteredQuery = $filter->apply($query, []);

    expect($filteredQuery)->toBeInstanceOf(Builder::class);
});

test('filter handles invalid workingstock value gracefully', function () {
    $this->actingAs($this->advisor);

    $filter = AdvisorWorkingstockFilter::make();
    $query = Zaak::query();

    // Apply filter with invalid workingstock value
    $filteredQuery = $filter->apply($query, ['workingstock-adv' => 'invalid']);

    expect($filteredQuery)->toBeInstanceOf(Builder::class);

    // Should not apply any filtering for invalid values
    $originalCount = Zaak::count();
    $filteredCount = $filteredQuery->count();

    expect($filteredCount)->toBe($originalCount);
});

test('filter works with multiple advice threads on same zaak', function () {
    $this->actingAs($this->advisor);
    Filament::setTenant($this->advisory);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Multi-thread Zaak'
        ),
    ]);

    // Create one unassigned active thread
    AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Asked,
        'title' => 'Multi-thread Unassigned',
        'created_by' => $this->advisor->id,
    ]);

    // Create another active thread assigned to current user
    $assignedThread = AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::InProgress,
        'title' => 'Multi-thread Assigned',
        'created_by' => $this->advisor->id,
    ]);

    $assignedThread->assignedUsers()->attach($this->advisor);

    // 'new' should still return the zaak because it has at least one unassigned active thread
    $query = Zaak::query();
    $filter = AdvisorWorkingstockFilter::make();
    $newFilterQuery = $filter->apply($query, ['workingstock-adv' => 'new']);
    $newResults = $newFilterQuery->get();

    expect($newResults)->toHaveCount(1)
        ->and($newResults->first()->id)->toBe($zaak->id);

    // 'me' should show zaak since user is assigned to one active thread
    $query = Zaak::query();
    $meFilterQuery = $filter->apply($query, ['workingstock-adv' => 'me']);
    $meResults = $meFilterQuery->get();

    expect($meResults)->toHaveCount(1)
        ->and($meResults->first()->id)->toBe($zaak->id);
});

test('filter form schema is configured correctly', function () {
    $filter = AdvisorWorkingstockFilter::make();
    $schema = $filter->getFormSchema();

    expect($schema)->toHaveCount(1);

    $toggleButtons = $schema[0];

    expect($toggleButtons->getName())->toBe('workingstock-adv')
        ->and($toggleButtons->getLabel())->toBe(__('resources/zaak.filters.workingstock.label'))
        ->and($toggleButtons->getDefaultState())->toBe('new');
});
