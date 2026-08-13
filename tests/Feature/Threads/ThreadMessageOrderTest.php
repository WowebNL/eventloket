<?php

use App\Enums\AdviceStatus;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    // Creating a message makes the observer read the zaak status from ZGW.
    Http::fake([ZgwHttpFake::$baseUrl.'*' => Http::response([], 200)]);

    // The message observer walks Zaak -> Zaaktype -> Municipality, so the
    // zaaktype needs a municipality behind it.
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => Municipality::factory()->create()->id,
    ]);

    $zaak = Zaak::factory()->create([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => $zaaktype->id,
    ]);

    // Concept keeps the observer from notifying an advisory this test does
    // not need; the ordering under test lives on the base Thread model.
    $this->thread = AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Advice,
        'advice_status' => AdviceStatus::Concept,
        'title' => 'Test advice thread',
    ]);
});

test('messages are returned in chronological order regardless of insertion order', function () {
    Message::factory()->create([
        'thread_id' => $this->thread->id,
        'body' => 'Antwoord van de adviesdienst',
        'created_at' => now()->subHour(),
    ]);

    Message::factory()->create([
        'thread_id' => $this->thread->id,
        'body' => 'Vraag van de behandelaar',
        'created_at' => now()->subDay(),
    ]);

    expect($this->thread->messages()->pluck('body')->all())->toBe([
        'Vraag van de behandelaar',
        'Antwoord van de adviesdienst',
    ]);
});

test('updating an earlier message does not move it to the end of the thread', function () {
    // The reported bug: PostgreSQL rewrites an updated row at the end of the
    // heap, so without an explicit ORDER BY the question ended up below the
    // reply that answered it.
    $vraag = Message::factory()->create([
        'thread_id' => $this->thread->id,
        'body' => 'Vraag van de behandelaar',
        'created_at' => now()->subDay(),
    ]);

    Message::factory()->create([
        'thread_id' => $this->thread->id,
        'body' => 'Antwoord van de adviesdienst',
        'created_at' => now()->subHour(),
    ]);

    $vraag->update(['documents' => [['url' => 'https://example.test/document/1', 'versie' => 1]]]);

    expect($this->thread->fresh()->messages->pluck('body')->all())->toBe([
        'Vraag van de behandelaar',
        'Antwoord van de adviesdienst',
    ]);
});

test('messages written within the same second keep their insertion order', function () {
    // created_at is a timestamp(0) column, so messages posted in quick
    // succession share a timestamp and need the id as tie-breaker.
    $moment = now()->startOfSecond();

    $first = Message::factory()->create([
        'thread_id' => $this->thread->id,
        'body' => 'Eerste',
        'created_at' => $moment,
    ]);

    $second = Message::factory()->create([
        'thread_id' => $this->thread->id,
        'body' => 'Tweede',
        'created_at' => $moment,
    ]);

    expect($first->id)->toBeLessThan($second->id)
        ->and($this->thread->messages()->pluck('body')->all())->toBe(['Eerste', 'Tweede']);
});

test('the latest message of a thread is the most recent one', function () {
    // The thread table column renders `$thread->messages->last()`, which only
    // holds up while the relation is ordered.
    Message::factory()->create([
        'thread_id' => $this->thread->id,
        'body' => 'Oudste',
        'created_at' => now()->subDay(),
    ]);

    $newest = Message::factory()->create([
        'thread_id' => $this->thread->id,
        'body' => 'Nieuwste',
        'created_at' => now(),
    ]);

    expect($this->thread->messages->last()->is($newest))->toBeTrue();
});
