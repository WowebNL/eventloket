<?php

use App\Models\FormsubmissionSession;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ObjectsApi\FormSubmissionObject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organisation = Organisation::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create();
});

test('returns prefill_data when prefillZaak exists', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    $formSubmissionObject = new FormSubmissionObject(
        uuid: (string) Str::uuid(),
        type: 'openforms.formsubmission',
        record: [
            'data' => [
                'data' => [
                    'prefill_key' => 'prefill_value',
                ],
            ],
        ],
    );

    Cache::forever("zaak.{$zaak->id}.zaakdata", $formSubmissionObject);

    $submission = FormsubmissionSession::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'prefill_zaak_reference' => $zaak->id,
    ]);

    $response = $this->withoutMiddleware()->getJson('/api/formsessions?submission_uuid='.$submission->uuid);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Valid session',
            'data' => [
                'prefill_data' => [
                    'prefill_key' => 'prefill_value',
                ],
            ],
        ]);
});

test('does not return prefill_data when prefillZaak is missing', function () {
    $submission = FormsubmissionSession::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'prefill_zaak_reference' => null,
    ]);

    $response = $this->withoutMiddleware()->getJson('/api/formsessions?submission_uuid='.$submission->uuid);

    $response->assertStatus(200)
        ->assertJsonMissingPath('data.prefill_data');
});
