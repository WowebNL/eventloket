<?php

use App\Enums\MunicipalityVariableType;
use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\ReportQuestions\Pages\ListReportQuestions;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use App\Models\ReportQuestion;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Laravel\Passport\Client;

use function Pest\Livewire\livewire;

test('report questions are automatically seeded when municipality is created', function () {
    $municipality = Municipality::factory()->create();
    $defaultQuestions = config('report-questions.defaults', []);

    expect($municipality->reportQuestions()->count())->toBe(count($defaultQuestions));
    $questions = $municipality->reportQuestions()->orderBy('order')->get();
    expect($questions->first()->order)->toBe(1);
    expect($questions->last()->order)->toBe(array_key_last($defaultQuestions));
    expect($questions->every(fn ($q) => $q->is_active))->toBeTrue();
    expect($questions->pluck('question', 'order')->all())->toBe($defaultQuestions);
});

test('report questions API returns questions for municipalities with new system', function () {
    $municipality = Municipality::factory()->create(['use_new_report_questions' => true]);

    // Delete auto-seeded questions and add custom test questions
    $municipality->reportQuestions()->delete();

    ReportQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 1,
        'question' => 'Question 1?',
        'is_active' => true,
    ]);

    ReportQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 2,
        'question' => 'Question 2?',
        'is_active' => true,
    ]);

    ReportQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 3,
        'question' => 'Question 3?',
        'is_active' => false,
    ]);

    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);
    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
    ]);
    $accessToken = $response->json('access_token');

    $response = $this->getJson(route('api.report-questions', $municipality->brk_identification), [
        'Authorization' => 'Bearer '.$accessToken,
    ]);

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    expect($data)->toHaveKeys(['1', '2']);
    expect($data['1'])->toBe('Question 1?');
    expect($data['2'])->toBe('Question 2?');
    expect(array_key_exists('3', $data))->toBeFalse();
});

test('report questions API returns empty for municipalities with old system', function () {
    $municipality = Municipality::factory()->create(['use_new_report_questions' => false]);

    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);
    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
    ]);
    $accessToken = $response->json('access_token');

    $response = $this->getJson(route('api.report-questions', $municipality->brk_identification), [
        'Authorization' => 'Bearer '.$accessToken,
    ]);

    $response->assertStatus(200);
    expect($response->json('data'))->toBeEmpty();
});

test('municipality variables API excludes report questions for municipalities with new system', function () {
    $municipality = Municipality::factory()->create(['use_new_report_questions' => true]);

    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MunicipalityVariableType::ReportQuestion,
        'key' => 'report_question_1',
        'value' => 'Old question?',
    ]);

    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MunicipalityVariableType::Text,
        'key' => 'some_text_var',
        'value' => 'Some text',
    ]);

    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);
    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
    ]);
    $accessToken = $response->json('access_token');

    $response = $this->getJson(route('api.municipality-variables', $municipality->brk_identification), [
        'Authorization' => 'Bearer '.$accessToken,
    ]);

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['key'])->toBe('some_text_var');
});

test('municipality variables API includes report questions for municipalities with old system', function () {
    $municipality = Municipality::factory()->create(['use_new_report_questions' => false]);

    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MunicipalityVariableType::ReportQuestion,
        'key' => 'report_question_1',
        'value' => 'Old question?',
    ]);

    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MunicipalityVariableType::Text,
        'key' => 'some_text_var',
        'value' => 'Some text',
    ]);

    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);
    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
    ]);
    $accessToken = $response->json('access_token');

    $response = $this->getJson(route('api.municipality-variables', $municipality->brk_identification), [
        'Authorization' => 'Bearer '.$accessToken,
    ]);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

test('admin users can view report questions', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $municipality = Municipality::factory()->create();
    $reportQuestion = $municipality->reportQuestions()->first();

    expect($admin->can('view', $reportQuestion))->toBeTrue();
    expect($admin->can('update', $reportQuestion))->toBeTrue();
    expect($admin->can('viewAny', ReportQuestion::class))->toBeTrue();
});

test('municipality admin can view and update their own municipality report questions', function () {
    $municipality = Municipality::factory()->create();
    $municipalityAdmin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $municipalityAdmin->municipalities()->attach($municipality);
    $reportQuestion = $municipality->reportQuestions()->first();

    expect($municipalityAdmin->can('view', $reportQuestion))->toBeTrue();
    expect($municipalityAdmin->can('update', $reportQuestion))->toBeTrue();
    expect($municipalityAdmin->can('viewAny', ReportQuestion::class))->toBeTrue();
});

test('municipality admin cannot view other municipality report questions', function () {
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();
    $municipalityAdmin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $municipalityAdmin->municipalities()->attach($municipality1);
    $reportQuestion = $municipality2->reportQuestions()->first();

    expect($municipalityAdmin->can('view', $reportQuestion))->toBeFalse();
    expect($municipalityAdmin->can('update', $reportQuestion))->toBeFalse();
});

test('report questions cannot be created or deleted via policy', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $municipality = Municipality::factory()->create();
    $reportQuestion = $municipality->reportQuestions()->first();

    expect($admin->can('create', ReportQuestion::class))->toBeFalse();
    expect($admin->can('delete', $reportQuestion))->toBeFalse();
});

test('report questions unique constraint prevents duplicate order per municipality', function () {
    $municipality = Municipality::factory()->create();
    $municipality->reportQuestions()->delete();
    ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1]);

    expect(fn () => ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1]))
        ->toThrow(QueryException::class);
});

test('different municipalities can have same order numbers', function () {
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    $question1 = $municipality1->reportQuestions->first();
    $question2 = $municipality2->reportQuestions->first();

    expect($question1)->toBeInstanceOf(ReportQuestion::class);
    expect($question2)->toBeInstanceOf(ReportQuestion::class);
});

test('report questions are properly ordered in API response', function () {
    $municipality = Municipality::factory()->create(['use_new_report_questions' => true]);

    // Delete auto-seeded questions and create questions out of order
    $municipality->reportQuestions()->delete();

    ReportQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 3,
        'question' => 'Third question?',
        'is_active' => true,
    ]);

    ReportQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 1,
        'question' => 'First question?',
        'is_active' => true,
    ]);

    ReportQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => 2,
        'question' => 'Second question?',
        'is_active' => true,
    ]);

    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);
    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
    ]);
    $accessToken = $response->json('access_token');

    $response = $this->getJson(route('api.report-questions', $municipality->brk_identification), [
        'Authorization' => 'Bearer '.$accessToken,
    ]);

    $data = $response->json('data');
    $keys = array_keys($data);
    expect($keys)->toBe([1, 2, 3]);
    expect($data['1'])->toBe('First question?');
    expect($data['2'])->toBe('Second question?');
    expect($data['3'])->toBe('Third question?');
});

test('reordering report questions persists new order', function () {
    $municipality = Municipality::factory()->create();
    $municipality->reportQuestions()->delete();

    $q1 = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1, 'question' => 'First?']);
    $q2 = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 2, 'question' => 'Second?']);
    $q3 = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 3, 'question' => 'Third?']);

    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    // New order: move q3 to position 1
    livewire(ListReportQuestions::class)
        ->call('reorderTable', [$q3->id, $q1->id, $q2->id]);

    expect($q3->fresh()->order)->toBe(1);
    expect($q1->fresh()->order)->toBe(2);
    expect($q2->fresh()->order)->toBe(3);
});

test('reordering does not cause unique constraint violations', function () {
    $municipality = Municipality::factory()->create();
    $municipality->reportQuestions()->delete();

    $questions = collect(range(1, 5))->map(fn ($i) => ReportQuestion::factory()->create([
        'municipality_id' => $municipality->id,
        'order' => $i,
        'question' => "Question $i?",
    ]));

    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    // Reverse the entire order (worst case for constraint violations)
    $reversed = $questions->reverse()->pluck('id')->all();

    livewire(ListReportQuestions::class)
        ->call('reorderTable', $reversed);

    foreach ($questions->reverse()->values() as $newPosition => $question) {
        expect($question->fresh()->order)->toBe($newPosition + 1);
    }
});

test('reordering does not affect report questions of other municipalities', function () {
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    $municipality1->reportQuestions()->delete();
    $municipality2->reportQuestions()->delete();

    $q1 = ReportQuestion::factory()->create(['municipality_id' => $municipality1->id, 'order' => 1, 'question' => 'Q1?']);
    $q2 = ReportQuestion::factory()->create(['municipality_id' => $municipality1->id, 'order' => 2, 'question' => 'Q2?']);
    $other = ReportQuestion::factory()->create(['municipality_id' => $municipality2->id, 'order' => 1, 'question' => 'Other?']);

    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality1);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality1);

    livewire(ListReportQuestions::class)
        ->call('reorderTable', [$q2->id, $q1->id]);

    expect($q2->fresh()->order)->toBe(1);
    expect($q1->fresh()->order)->toBe(2);
    expect($other->fresh()->order)->toBe(1); // untouched
});

// ---------------------------------------------------------------------------
// ReportQuestionsTable configuration
// ---------------------------------------------------------------------------

test('report questions table has required columns', function () {
    $municipality = Municipality::factory()->create();
    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    livewire(ListReportQuestions::class)
        ->assertTableColumnExists('order')
        ->assertTableColumnExists('question')
        ->assertTableColumnExists('is_active')
        ->assertTableColumnExists('updated_at');
});

test('report questions table is sorted by order ascending by default', function () {
    $municipality = Municipality::factory()->create();
    $municipality->reportQuestions()->delete();

    $c = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 3, 'question' => 'C?']);
    $a = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1, 'question' => 'A?']);
    $b = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 2, 'question' => 'B?']);

    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    livewire(ListReportQuestions::class)
        ->assertCanSeeTableRecords([$a, $b, $c], inOrder: true);
});

test('report questions table can be put into reorder mode', function () {
    $municipality = Municipality::factory()->create();
    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    livewire(ListReportQuestions::class)
        ->call('toggleTableReordering')
        ->assertSet('isTableReordering', true);
});

test('table reorder action updates record order in the database', function () {
    $municipality = Municipality::factory()->create();
    $municipality->reportQuestions()->delete();

    $q1 = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1, 'question' => 'First?']);
    $q2 = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 2, 'question' => 'Second?']);
    $q3 = ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 3, 'question' => 'Third?']);

    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    // Move q3 to the top
    livewire(ListReportQuestions::class)
        ->call('reorderTable', [$q3->id, $q1->id, $q2->id]);

    expect($q3->fresh()->order)->toBe(1);
    expect($q1->fresh()->order)->toBe(2);
    expect($q2->fresh()->order)->toBe(3);
});

test('report questions table question column is searchable', function () {
    $municipality = Municipality::factory()->create();
    $municipality->reportQuestions()->delete();

    ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 1, 'question' => 'Unique searchable question?']);
    ReportQuestion::factory()->create(['municipality_id' => $municipality->id, 'order' => 2, 'question' => 'Other question?']);

    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    livewire(ListReportQuestions::class)
        ->searchTable('Unique searchable')
        ->assertCountTableRecords(1);
});

test('report questions table edit record action exists', function () {
    $municipality = Municipality::factory()->create();
    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    livewire(ListReportQuestions::class)
        ->assertTableActionExists('edit');
});

// ---------------------------------------------------------------------------
// ListReportQuestions – use_new_report_questions toggle
// ---------------------------------------------------------------------------

test('list page shows use_new_report_questions toggle reflecting current state', function () {
    $municipality = Municipality::factory()->create(['use_new_report_questions' => false]);
    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    livewire(ListReportQuestions::class)
        ->assertSet('municipalitySettings.use_new_report_questions', false);
});

test('toggling use_new_report_questions to true persists on municipality', function () {
    $municipality = Municipality::factory()->create(['use_new_report_questions' => false]);
    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    livewire(ListReportQuestions::class)
        ->set('municipalitySettings.use_new_report_questions', true);

    expect($municipality->fresh()->use_new_report_questions)->toBeTrue();
});

test('toggling use_new_report_questions to false persists on municipality', function () {
    $municipality = Municipality::factory()->create(['use_new_report_questions' => true]);
    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $user->municipalities()->attach($municipality);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    livewire(ListReportQuestions::class)
        ->set('municipalitySettings.use_new_report_questions', false);

    expect($municipality->fresh()->use_new_report_questions)->toBeFalse();
});
