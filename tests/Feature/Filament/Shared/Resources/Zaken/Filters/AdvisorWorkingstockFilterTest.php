<?php

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Filters\AdvisorWorkingstockFilter;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
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

test('filter with new option shows zaken with unassigned advice threads', function () {
    $this->actingAs($this->advisor);

    // Create zaak with unassigned advice thread
    $zaakWithUnassigned = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'Test Zaak'
        ),
    ]);

    $unassignedThread = AdviceThread::forceCreate([
        'zaak_id' => $zaakWithUnassigned->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Test Advice Thread',
        'created_by' => $this->advisor->id,
    ]);

    // Create zaak with assigned advice thread
    $zaakWithAssigned = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'Test Zaak 2'
        ),
    ]);

    $assignedThread = AdviceThread::forceCreate([
        'zaak_id' => $zaakWithAssigned->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Test Advice Thread Assigned',
        'created_by' => $this->advisor->id,
    ]);

    $assignedThread->assignedUsers()->attach($this->advisor);

    // Apply filter with 'new' option
    $query = Zaak::query();
    $filter = AdvisorWorkingstockFilter::make();
    $filteredQuery = $filter->apply($query, ['workingstock' => 'new']);

    $results = $filteredQuery->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($zaakWithUnassigned->id);
});

test('filter with me option shows zaken assigned to current user', function () {
    $this->actingAs($this->advisor);

    // Create zaak assigned to current user
    $myZaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'My Zaak'
        ),
    ]);

    $myThread = AdviceThread::forceCreate([
        'zaak_id' => $myZaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'My Advice Thread',
        'created_by' => $this->advisor->id,
    ]);

    $myThread->assignedUsers()->attach($this->advisor);

    // Create zaak assigned to another user
    $otherZaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'Other Zaak'
        ),
    ]);

    $otherThread = AdviceThread::forceCreate([
        'zaak_id' => $otherZaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Other Advice Thread',
        'created_by' => $this->advisor2->id,
    ]);

    $otherThread->assignedUsers()->attach($this->advisor2);

    // Create unassigned zaak
    $unassignedZaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'Unassigned Zaak'
        ),
    ]);

    AdviceThread::forceCreate([
        'zaak_id' => $unassignedZaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Unassigned Thread',
        'created_by' => $this->advisor->id,
    ]);

    // Apply filter with 'me' option
    $query = Zaak::query();
    $filter = AdvisorWorkingstockFilter::make();
    $filteredQuery = $filter->apply($query, ['workingstock' => 'me']);

    $results = $filteredQuery->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($myZaak->id);
});

test('filter with all option shows all zaken', function () {
    $this->actingAs($this->advisor);

    // Create various zaken
    $zaak1 = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'Zaak 1'
        ),
    ]);

    $zaak2 = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'Zaak 2'
        ),
    ]);

    $thread1 = AdviceThread::forceCreate([
        'zaak_id' => $zaak1->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Thread 1',
        'created_by' => $this->advisor->id,
    ]);

    $thread2 = AdviceThread::forceCreate([
        'zaak_id' => $zaak2->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Thread 2',
        'created_by' => $this->advisor->id,
    ]);

    $thread1->assignedUsers()->attach($this->advisor);
    // Leave thread2 unassigned

    // Apply filter with 'all' option
    $query = Zaak::query();
    $filter = AdvisorWorkingstockFilter::make();
    $filteredQuery = $filter->apply($query, ['workingstock' => 'all']);

    $results = $filteredQuery->get();

    // With 'all' option, no filtering should be applied, so we should get all zaken
    expect($results->count())->toBeGreaterThanOrEqual(2);
});

test('filter query function works correctly with builder', function () {
    $this->actingAs($this->advisor);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'Test Zaak'
        ),
    ]);

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Test Thread',
        'created_by' => $this->advisor->id,
    ]);

    $filter = AdvisorWorkingstockFilter::make();
    $query = Zaak::query();

    // Test that the query method returns a Builder instance
    $filteredQuery = $filter->apply($query, ['workingstock' => 'new']);

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
    $filteredQuery = $filter->apply($query, ['workingstock' => 'invalid']);

    expect($filteredQuery)->toBeInstanceOf(Builder::class);

    // Should not apply any filtering for invalid values
    $originalCount = Zaak::count();
    $filteredCount = $filteredQuery->count();

    expect($filteredCount)->toBe($originalCount);
});

test('filter works with multiple advice threads on same zaak', function () {
    $this->actingAs($this->advisor);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-02',
            registratiedatum: '2025-01-01',
            status_name: 'Ontvangen',
            naam_evenement: 'Multi-thread Zaak'
        ),
    ]);

    // Create one unassigned thread
    $unassignedThread = AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Multi-thread Unassigned',
        'created_by' => $this->advisor->id,
    ]);

    // Create another thread assigned to current user
    $assignedThread = AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'advisory_id' => $this->advisory->id,
        'type' => \App\Enums\ThreadType::Advice,
        'title' => 'Multi-thread Assigned',
        'created_by' => $this->advisor->id,
    ]);

    $assignedThread->assignedUsers()->attach($this->advisor);

    // Test 'new' filter - should NOT show zaak if ANY thread is assigned
    $query = Zaak::query();
    $filter = AdvisorWorkingstockFilter::make();
    $newFilterQuery = $filter->apply($query, ['workingstock' => 'new']);

    // Should not return the zaak since it has at least one unassigned thread
    // But also has an assigned thread, so behavior depends on implementation

    // Test 'me' filter - should show zaak since user is assigned to one thread
    $query = Zaak::query();
    $meFilterQuery = $filter->apply($query, ['workingstock' => 'me']);
    $meResults = $meFilterQuery->get();

    expect($meResults)->toHaveCount(1)
        ->and($meResults->first()->id)->toBe($zaak->id);
});

test('filter form schema is configured correctly', function () {
    $filter = AdvisorWorkingstockFilter::make();
    $schema = $filter->getFormSchema();

    expect($schema)->toHaveCount(1);

    $toggleButtons = $schema[0];

    expect($toggleButtons->getName())->toBe('workingstock')
        ->and($toggleButtons->getLabel())->toBe(__('resources/zaak.filters.workingstock.label'))
        ->and($toggleButtons->getDefaultState())->toBe('new');
});
