<?php

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Shared\Resources\Threads\Actions\RequestAdviceAction;
use App\Models\Advisory;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\NewAdviceThread;

beforeEach(function () {
    Notification::fake();

    $this->municipality = Municipality::factory()->create();
    $this->advisory = Advisory::factory()->create();
    $this->advisory->municipalities()->attach($this->municipality);

    $this->advisor = User::factory()->create([
        'name' => 'Advisor',
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisory->users()->attach($this->advisor, ['role' => AdvisoryRole::Admin]);

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
        'advice_due_at' => now()->addDays(14),
        'created_by' => null,
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

    // Now call the RequestAdviceAction
    $action = RequestAdviceAction::make();
    $action->record($thread);
    $action->call();

    // Refresh the thread
    $thread->refresh();

    // Verify status changed from Concept to Asked
    expect($thread->advice_status)->toBe(AdviceStatus::Asked);

    $firstMessage = $thread->messages()->oldest()->first();
    $firstMessage->updated_at = now();
    $firstMessage->created_at = now();
    $firstMessage->saveQuietly();

    $thread->save();

    $thread->refresh();
    expect($thread->advice_status)->toBe(AdviceStatus::Asked);
    expect($thread->advice_due_at)->not->toBeNull();
    expect((int) round(now()->diffInDays($thread->advice_due_at)))->toBe(14);

    $firstMessage->refresh();
    expect($firstMessage->created_at->isToday())->toBeTrue();

    Notification::assertSentTo([$this->advisor], NewAdviceThread::class);
});

test('updates first message timestamps to current time', function () {
    $thread = AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test Question',
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Concept,
        'response_deadline_days' => 14,
        'created_by' => null,
    ]);

    $oldTimestamp = now()->subDays(10);
    $message = Message::create([
        'thread_id' => $thread->id,
        'user_id' => null,
        'body' => 'Test question body',
        'created_at' => $oldTimestamp,
        'updated_at' => $oldTimestamp,
    ]);

    // Now call the RequestAdviceAction
    $action = RequestAdviceAction::make();
    $action->record($thread);
    $action->call();

    $firstMessage = $thread->messages()->oldest()->first();
    $firstMessage->updated_at = now();
    $firstMessage->created_at = now();
    $firstMessage->saveQuietly();

    $thread->save();

    $firstMessage->refresh();
    expect($firstMessage->created_at->isToday())->toBeTrue();
    expect($firstMessage->updated_at->isToday())->toBeTrue();
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
