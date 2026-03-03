<?php

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->advisory = Advisory::factory()->create([
        'name' => 'Brandweer',
    ]);

    $this->advisoryAdmin = User::factory()->create([
        'email' => 'admin@advisory.com',
        'role' => Role::Advisor,
    ]);

    $this->advisor = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisor2 = User::factory()->create([
        'email' => 'advisor2@example.com',
        'role' => Role::Advisor,
    ]);

    $this->advisory->users()->attach($this->advisoryAdmin, ['role' => AdvisoryRole::Admin]);
    $this->advisory->users()->attach($this->advisor, ['role' => AdvisoryRole::Member]);
    $this->advisory->users()->attach($this->advisor2, ['role' => AdvisoryRole::Member]);

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
    ]);

    Mail::fake();
    Notification::fake();
});

it('SendAdviceReminders notifies advisory admin users for unassigned threads', function () {
    // Unassigned threads - should notify only advisory admin users
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

    // Only advisory admin users receive reminders for unassigned threads
    foreach ([5 => $t5, 3 => $t3, 0 => $t0] as $days => $thread) {
        Notification::assertSentTo(
            $this->advisoryAdmin,
            AdviceReminder::class,
            function (AdviceReminder $notification) use ($thread, $days) {
                // Check subject contains event + the relative time phrase
                $mail = $notification->toMail($this->advisoryAdmin);
                $when = trans_choice('notification/advice-reminder.when', $days, ['count' => $days]);

                return Str::contains($mail->subject, $thread->zaak->reference_data->naam_evenement)
                    && Str::contains($mail->subject, $when);
            }
        );

        // Regular advisory members should NOT receive reminders for unassigned threads
        Notification::assertNotSentTo(
            [$this->advisor, $this->advisor2],
            AdviceReminder::class
        );
    }
});

it('SendAdviceReminders notifies only assigned users for assigned threads', function () {
    // Assigned thread - should notify only assigned users
    $assignedThread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'advisory_id' => $this->advisory->id,
        'advice_status' => AdviceStatus::Asked,
        'advice_due_at' => now()->addDays(3),
        'created_by' => $this->reviewer->id,
        'title' => 'Assigned advice thread',
    ]);

    // Assign specific advisor to the thread
    $assignedThread->assignedUsers()->attach($this->advisor);

    // Run job
    (new SendAdviceReminders)->handle();

    // Only the assigned advisor receives the reminder
    Notification::assertSentTo(
        $this->advisor,
        AdviceReminder::class,
        function (AdviceReminder $notification) use ($assignedThread) {
            $mail = $notification->toMail($this->advisor);

            return Str::contains($mail->subject, $assignedThread->zaak->reference_data->naam_evenement);
        }
    );

    // Other advisory users should NOT receive reminders
    Notification::assertNotSentTo(
        [$this->advisoryAdmin, $this->advisor2],
        AdviceReminder::class
    );
});
