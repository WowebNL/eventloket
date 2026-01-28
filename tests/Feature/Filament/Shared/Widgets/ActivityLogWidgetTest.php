<?php

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Shared\Resources\Zaken\Widgets\ActivityLogWidget;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\ZgwHttpFake;

covers(ActivityLogWidget::class);

beforeEach(function (): void {
    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);
    $this->organisation = Organisation::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    ZgwHttpFake::fakeStatustypen();
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    $this->user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($this->user);
    $this->actingAs($this->user);
});

/**
 * Helper function to get activities from the widget
 */
function getWidgetActivities(Zaak $zaak): \Illuminate\Support\Collection
{
    $widget = new ActivityLogWidget;
    $widget->record = $zaak;

    $table = new \Filament\Tables\Table($widget);
    $configuredTable = $widget->table($table);

    return $configuredTable->getQuery()->get();
}

test('widget shows activities for the zaak itself', function () {
    // Clear any activities from zaak creation
    Activity::query()->delete();

    // Create activity log for the zaak
    activity('test')
        ->performedOn($this->zaak)
        ->log('Zaak was viewed');

    $activities = getWidgetActivities($this->zaak);

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->subject_id)->toBe($this->zaak->id)
        ->and($activities->first()->subject_type)->toBe(Zaak::class);
});

test('widget shows activities for advice threads belonging to the zaak', function () {
    // Clear any activities from zaak creation
    Activity::query()->delete();

    // Create an advice thread
    $adviceThread = AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test Advice Thread',
        'created_by' => $this->user->id,
        'advice_status' => AdviceStatus::Concept,
    ]);

    // Create activity for the thread
    activity('test')
        ->performedOn($adviceThread)
        ->log('Advice thread was created');

    $activities = getWidgetActivities($this->zaak);

    expect($activities->count())->toBeGreaterThanOrEqual(1);

    // Verify that at least one activity is for the advice thread
    $adviceThreadActivities = $activities->where('subject_type', AdviceThread::class)
        ->where('subject_id', (string) $adviceThread->id);

    expect($adviceThreadActivities->count())->toBeGreaterThanOrEqual(1);
});

test('widget shows activities for organiser threads belonging to the zaak', function () {
    // Clear any activities from zaak creation
    Activity::query()->delete();

    // Create an organiser thread
    $organiserThread = OrganiserThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'title' => 'Test Organiser Thread',
        'created_by' => $this->user->id,
    ]);

    // Create activity for the thread
    activity('test')
        ->performedOn($organiserThread)
        ->log('Organiser thread was created');

    $activities = getWidgetActivities($this->zaak);

    expect($activities->count())->toBeGreaterThanOrEqual(1);

    // Verify that at least one activity is for the organiser thread
    $organiserThreadActivities = $activities->where('subject_type', OrganiserThread::class)
        ->where('subject_id', (string) $organiserThread->id);

    expect($organiserThreadActivities->count())->toBeGreaterThanOrEqual(1);
});

test('widget shows activities for messages in threads belonging to the zaak', function () {
    // Clear any activities from zaak creation
    Activity::query()->delete();

    // Create a thread and message
    $thread = OrganiserThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'title' => 'Test Thread',
        'created_by' => $this->user->id,
    ]);

    $message = Message::create([
        'thread_id' => $thread->id,
        'user_id' => $this->user->id,
        'body' => 'Test message',
    ]);

    // Create activity for the message
    activity('test')
        ->performedOn($message)
        ->log('Message was sent');

    $activities = getWidgetActivities($this->zaak);

    expect($activities->count())->toBeGreaterThanOrEqual(1);

    // Verify that at least one activity is for the message
    $messageActivities = $activities->where('subject_type', Message::class)
        ->where('subject_id', (string) $message->id);

    expect($messageActivities->count())->toBeGreaterThanOrEqual(1);
});

test('widget combines activities from zaak, threads, and messages', function () {
    // Clear any activities from zaak creation
    Activity::query()->delete();

    // Create activity for zaak
    activity('test')
        ->performedOn($this->zaak)
        ->log('Zaak was viewed');

    // Create advice thread with activity
    $adviceThread = AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test Advice Thread',
        'created_by' => $this->user->id,
        'advice_status' => AdviceStatus::Concept,
    ]);

    activity('test')
        ->performedOn($adviceThread)
        ->log('Advice thread was created');

    // Create organiser thread with message
    $organiserThread = OrganiserThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'title' => 'Test Organiser Thread',
        'created_by' => $this->user->id,
    ]);

    $message = Message::create([
        'thread_id' => $organiserThread->id,
        'user_id' => $this->user->id,
        'body' => 'Test message',
    ]);

    activity('test')
        ->performedOn($message)
        ->log('Message was sent');

    $activities = getWidgetActivities($this->zaak);

    // Should have activities from zaak, advice thread, organiser thread, and message
    // (including auto-logged created events)
    expect($activities->count())->toBeGreaterThanOrEqual(3);

    // Verify each type has at least one activity
    expect($activities->where('subject_type', Zaak::class)->count())->toBeGreaterThanOrEqual(1);
    expect($activities->where('subject_type', AdviceThread::class)->count())->toBeGreaterThanOrEqual(1);
    expect($activities->where('subject_type', Message::class)->count())->toBeGreaterThanOrEqual(1);
});

test('widget does not show activities for threads of other zaken', function () {
    // Clear any activities from zaak creation
    Activity::query()->delete();

    // Create another zaak
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->addDays(30)->toIso8601String(),
            eind_evenement: now()->addDays(31)->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    // Create thread for other zaak
    $otherThread = OrganiserThread::create([
        'zaak_id' => $otherZaak->id,
        'type' => ThreadType::Organiser,
        'title' => 'Other Thread',
        'created_by' => $this->user->id,
    ]);

    activity('test')
        ->performedOn($otherThread)
        ->log('Other thread was created');

    // Create activity for our zaak
    activity('test')
        ->performedOn($this->zaak)
        ->log('Our zaak was viewed');

    $activities = getWidgetActivities($this->zaak);

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->subject_type)->toBe(Zaak::class)
        ->and($activities->first()->subject_id)->toBe($this->zaak->id);
});

test('widget does not show activities for messages in threads of other zaken', function () {
    // Clear any activities from zaak creation
    Activity::query()->delete();

    // Create another zaak with thread and message
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->addDays(30)->toIso8601String(),
            eind_evenement: now()->addDays(31)->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    $otherThread = OrganiserThread::create([
        'zaak_id' => $otherZaak->id,
        'type' => ThreadType::Organiser,
        'title' => 'Other Thread',
        'created_by' => $this->user->id,
    ]);

    $otherMessage = Message::create([
        'thread_id' => $otherThread->id,
        'user_id' => $this->user->id,
        'body' => 'Other message',
    ]);

    activity('test')
        ->performedOn($otherMessage)
        ->log('Other message was sent');

    // Create activity for our zaak
    activity('test')
        ->performedOn($this->zaak)
        ->log('Our zaak was viewed');

    $activities = getWidgetActivities($this->zaak);

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->subject_type)->toBe(Zaak::class)
        ->and($activities->first()->subject_id)->toBe($this->zaak->id);
});

test('widget handles empty result when no activities exist', function () {
    // Clear any activities from zaak creation
    Activity::query()->delete();

    $activities = getWidgetActivities($this->zaak);

    expect($activities)->toHaveCount(0);
});
