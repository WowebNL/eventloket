<?php

use App\Enums\AdviceStatus;
use App\Jobs\Zaak\CreateConceptAdviceQuestions;
use App\Models\Advisory;
use App\Models\DefaultAdviceQuestion;
use App\Models\Municipality;
use App\Models\Threads\AdviceThread;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Notification::fake();

    $this->municipality = Municipality::factory()->create();
    $this->advisory = Advisory::factory()->create();
    $this->advisory->municipalities()->attach($this->municipality);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    ZgwHttpFake::fakeStatustypen();
});

test('creates concept advice threads when risico classificatie matches', function () {
    $defaultQuestion = DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'advisory_id' => $this->advisory->id,
        'risico_classificatie' => 'B',
        'title' => 'Test Question B',
        'description' => 'Test Description B',
        'response_deadline_days' => 14,
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->toDateTimeString(),
            eind_evenement: now()->addDay()->toDateTimeString(),
            registratiedatum: now()->toDateTimeString(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            risico_classificatie: 'B',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test event'
        ),
    ]);

    // Default question is created in the observer

    expect(AdviceThread::count())->toBe(1);

    $thread = AdviceThread::first();
    expect($thread)
        ->zaak_id->toBe($zaak->id)
        ->advisory_id->toBe($this->advisory->id)
        ->title->toBe('Test Question B')
        ->advice_status->toBe(AdviceStatus::Concept);
    //        ->advice_due_at->toBe(14);

    expect($thread->messages()->count())->toBe(1);
    expect($thread->messages()->first()->body)->toBe('Test Description B');

    // Advice thread is still in concept so no mails should have been sent
    Notification::assertNothingSent();
});

test('creates multiple concept threads for multiple default questions', function () {
    $advisory2 = Advisory::factory()->create();
    $advisory2->municipalities()->attach($this->municipality);

    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'advisory_id' => $this->advisory->id,
        'risico_classificatie' => 'A',
        'title' => 'Question 1',
    ]);

    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'advisory_id' => $advisory2->id,
        'risico_classificatie' => 'A',
        'title' => 'Question 2',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->toDateTimeString(),
            eind_evenement: now()->addDay()->toDateTimeString(),
            registratiedatum: now()->toDateTimeString(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            risico_classificatie: 'A',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test event'
        ),
    ]);

    expect(AdviceThread::count())->toBe(2);
    expect(AdviceThread::where('advisory_id', $this->advisory->id)->count())->toBe(1);
    expect(AdviceThread::where('advisory_id', $advisory2->id)->count())->toBe(1);

    // Advice thread is still in concept so no mails should have been sent
    Notification::assertNothingSent();
});

test('does not create threads when no matching risico classificatie', function () {
    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'advisory_id' => $this->advisory->id,
        'risico_classificatie' => 'C',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->toDateTimeString(),
            eind_evenement: now()->addDay()->toDateTimeString(),
            registratiedatum: now()->toDateTimeString(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            risico_classificatie: 'A',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test event'
        ),
    ]);

    $job = new CreateConceptAdviceQuestions($zaak);
    $job->handle();

    expect(AdviceThread::count())->toBe(0);

    // Advice thread is still in concept so no mails should have been sent
    Notification::assertNothingSent();
});

test('does not create threads when zaak has no risico classificatie', function () {
    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'advisory_id' => $this->advisory->id,
        'risico_classificatie' => 'A',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->toDateTimeString(),
            eind_evenement: now()->addDay()->toDateTimeString(),
            registratiedatum: now()->toDateTimeString(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test event'
        ),
    ]);

    expect(AdviceThread::count())->toBe(0);

    // Advice thread is still in concept so no mails should have been sent
    Notification::assertNothingSent();
});

test('only creates threads for matching municipality', function () {
    $municipality2 = Municipality::factory()->create();
    $zaaktype2 = Zaaktype::factory()->create([
        'municipality_id' => $municipality2->id,
    ]);

    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'advisory_id' => $this->advisory->id,
        'risico_classificatie' => 'A',
        'title' => 'Municipality 1 Question',
    ]);

    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $municipality2->id,
        'advisory_id' => $this->advisory->id,
        'risico_classificatie' => 'A',
        'title' => 'Municipality 2 Question',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->toDateTimeString(),
            eind_evenement: now()->addDay()->toDateTimeString(),
            registratiedatum: now()->toDateTimeString(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            risico_classificatie: 'A',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test event'
        ),
    ]);

    expect(AdviceThread::count())->toBe(1);
    expect(AdviceThread::first()->title)->toBe('Municipality 1 Question');

    // Advice thread is still in concept so no mails should have been sent
    Notification::assertNothingSent();
});
