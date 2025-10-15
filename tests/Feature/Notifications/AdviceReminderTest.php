<?php

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Jobs\SendAdviceReminders;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Notifications\AdviceReminder;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->advisory = Advisory::factory()->create([
        'name' => 'Brandweer',
    ]);

    $this->advisor = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisor2 = User::factory()->create([
        'email' => 'advisor2@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisory->users()->attach($this->advisor);
    $this->advisory->users()->attach($this->advisor2);

    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);

    $this->reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->reviewer2 = User::factory()->create(['role' => Role::Reviewer]);

    $this->municipality->users()->attach($this->reviewer);
    $this->municipality->users()->attach($this->reviewer2);

    $this->organisation = Organisation::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new ZaakReferenceData(
            'A',
            now(),
            now()->addDay(),
            now(),
            'Ontvangen',
            'Test locatie',
            'Test event'
        ),
    ]);

    Mail::fake();
    Notification::fake();
});

it('SendAdviceReminders notifies advisory users for due threads at 5/3/0 days', function () {
    // Matching threads
    $t5 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(5),
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);
    $t3 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(3),
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);
    $t0 = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(0),
        'created_by' => $this->reviewer->id,
        'title' => 'Test advice thread',
    ]);

    // Run job
    (new SendAdviceReminders)->handle();

    // All advisory users on each matching thread receive exactly one notification
    foreach ([5 => $t5, 3 => $t3, 0 => $t0] as $days => $thread) {
        foreach ($thread->advisory->users as $user) {
            Notification::assertSentTo(
                $user,
                AdviceReminder::class,
                function (AdviceReminder $notification) use ($user, $thread, $days) {
                    // Check subject contains event + the relative time phrase
                    $mail = $notification->toMail($user);
                    $when = trans_choice('notification/advice-reminder.when', $days, ['count' => $days]);

                    return Str::contains($mail->subject, $thread->zaak->reference_data->naam_evenement)
                        && Str::contains($mail->subject, $when);
                }
            );
        }
    }
});
