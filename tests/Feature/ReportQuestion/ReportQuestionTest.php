<?php

use App\Enums\MunicipalityVariableType;
use App\Enums\Role;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use App\Models\ReportQuestion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Laravel\Passport\Client;

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
