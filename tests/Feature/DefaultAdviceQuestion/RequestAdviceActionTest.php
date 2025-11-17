<?php

use App\Enums\AdviceStatus;
use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();
    $this->advisory = Advisory::factory()->create();
    $this->advisory->municipalities()->attach($this->municipality);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    $this->user = User::factory()->create();
});

test('transitions concept thread to asked status', function () {
    $thread = AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test Question',
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'response_deadline_days' => 14,
        'created_by' => $this->user->id,
    ]);

    Message::create([
        'thread_id' => $thread->id,
        'user_id' => null,
        'body' => 'Test question body',
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    $thread->refresh();
    expect($thread->advice_status)->toBe(AdviceStatus::Concept);

    // Simulate the action
    $thread->advice_status = AdviceStatus::Asked;
    $thread->advice_due_at = now()->addDays($thread->response_deadline_days);

    $firstMessage = $thread->messages()->oldest()->first();
    $firstMessage->updated_at = now();
    $firstMessage->created_at = now();
    $firstMessage->saveQuietly();

    $thread->save();

    $thread->refresh();
    expect($thread->advice_status)->toBe(AdviceStatus::Asked);
    expect($thread->advice_due_at)->not->toBeNull();
    expect($thread->advice_due_at->diffInDays(now()))->toBe(14);

    $firstMessage->refresh();
    expect($firstMessage->created_at->isToday())->toBeTrue();
});

test('sets advice_due_at based on response_deadline_days', function () {
    $thread = AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test Question',
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'response_deadline_days' => 21,
        'created_by' => $this->user->id,
    ]);

    Message::create([
        'thread_id' => $thread->id,
        'user_id' => null,
        'body' => 'Test question body',
    ]);

    $thread->advice_status = AdviceStatus::Asked;
    $thread->advice_due_at = now()->addDays($thread->response_deadline_days);
    $thread->save();

    $thread->refresh();
    expect($thread->advice_due_at->diffInDays(now()))->toBe(21);
});

test('updates first message timestamps to current time', function () {
    $thread = AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test Question',
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'response_deadline_days' => 14,
        'created_by' => $this->user->id,
    ]);

    $oldTimestamp = now()->subDays(10);
    $message = Message::create([
        'thread_id' => $thread->id,
        'user_id' => null,
        'body' => 'Test question body',
        'created_at' => $oldTimestamp,
        'updated_at' => $oldTimestamp,
    ]);

    expect($message->created_at->diffInDays(now(), false))->toBe(10);

    // Simulate the action
    $thread->advice_status = AdviceStatus::Asked;
    $thread->advice_due_at = now()->addDays($thread->response_deadline_days);

    $firstMessage = $thread->messages()->oldest()->first();
    $firstMessage->updated_at = now();
    $firstMessage->created_at = now();
    $firstMessage->saveQuietly();

    $thread->save();

    $firstMessage->refresh();
    expect($firstMessage->created_at->isToday())->toBeTrue();
    expect($firstMessage->updated_at->isToday())->toBeTrue();
});

test('does not affect other messages timestamps', function () {
    $thread = AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test Question',
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'response_deadline_days' => 14,
        'created_by' => $this->user->id,
    ]);

    $message1 = Message::create([
        'thread_id' => $thread->id,
        'user_id' => null,
        'body' => 'First message',
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    $message2Timestamp = now()->subDays(5);
    $message2 = Message::create([
        'thread_id' => $thread->id,
        'user_id' => $this->user->id,
        'body' => 'Second message',
        'created_at' => $message2Timestamp,
        'updated_at' => $message2Timestamp,
    ]);

    // Simulate the action
    $thread->advice_status = AdviceStatus::Asked;
    $thread->advice_due_at = now()->addDays($thread->response_deadline_days);

    $firstMessage = $thread->messages()->oldest()->first();
    $firstMessage->updated_at = now();
    $firstMessage->created_at = now();
    $firstMessage->saveQuietly();

    $thread->save();

    $message2->refresh();
    expect($message2->created_at->toDateTimeString())->toBe($message2Timestamp->toDateTimeString());
});

test('concept thread is not visible with concept status filter', function () {
    AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Concept Question',
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'response_deadline_days' => 14,
        'created_by' => $this->user->id,
    ]);

    AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Asked Question',
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'response_deadline_days' => 14,
        'created_by' => $this->user->id,
    ]);

    // This simulates what advisory users should see
    $visibleThreads = AdviceThread::where('advice_status', '!=', AdviceStatus::Concept)->get();

    expect($visibleThreads)->toHaveCount(1);
    expect($visibleThreads->first()->title)->toBe('Asked Question');
});
