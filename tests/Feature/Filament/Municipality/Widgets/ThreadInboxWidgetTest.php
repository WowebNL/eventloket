<?php

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Filament\Municipality\Pages\Dashboard;
use App\Filament\Municipality\Widgets\ThreadInboxWidget;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Thread;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

covers(ThreadInboxWidget::class);

/**
 * Helper to create an OrganiserThread without triggering observers
 * (which would attempt ZGW API HTTP calls in tests).
 */
function makeOrganiserThread(Zaak $zaak, array $attributes = []): OrganiserThread
{
    $thread = null;
    OrganiserThread::withoutEvents(function () use ($zaak, $attributes, &$thread) {
        $thread = OrganiserThread::forceCreate(array_merge([
            'zaak_id' => $zaak->id,
            'type' => ThreadType::Organiser,
            'title' => 'Organiser Thread',
        ], $attributes));
    });

    return $thread;
}

/**
 * Helper to create an AdviceThread without triggering observers.
 */
function makeAdviceThread(Zaak $zaak, Advisory $advisory, array $attributes = []): AdviceThread
{
    $thread = null;
    AdviceThread::withoutEvents(function () use ($zaak, $advisory, $attributes, &$thread) {
        $thread = AdviceThread::forceCreate(array_merge([
            'zaak_id' => $zaak->id,
            'type' => ThreadType::Advice,
            'advisory_id' => $advisory->id,
            'advice_status' => AdviceStatus::Asked,
            'title' => 'Advice Thread',
        ], $attributes));
    });

    return $thread;
}

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);
    $this->otherMunicipality = Municipality::factory()->create(['name' => 'Other Municipality']);
    $this->organisation = Organisation::factory()->create();
    $this->advisory = Advisory::factory()->create(['name' => 'Test Advisory']);

    $this->municipalityAdmin = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);
    $this->municipality->users()->attach($this->municipalityAdmin);

    $this->actingAs($this->municipalityAdmin);
    Filament::setTenant($this->municipality);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);
});

test('thread inbox widget renders successfully', function () {
    livewire(ThreadInboxWidget::class)
        ->assertOk();
});

test('thread inbox widget shows organiser threads for current municipality', function () {
    $thread = makeOrganiserThread($this->zaak, ['title' => 'My Organiser Thread']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', ['unread' => 'all'])
        ->assertCanSeeTableRecords([$thread]);
});

test('thread inbox widget shows advice threads for current municipality', function () {
    $this->advisory->municipalities()->attach($this->municipality);
    $thread = makeAdviceThread($this->zaak, $this->advisory, ['title' => 'My Advice Thread']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', ['unread' => 'all'])
        ->assertCanSeeTableRecords([$thread]);
});

test('thread inbox widget does not show threads from other municipalities', function () {
    $otherZaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->otherMunicipality->id,
    ]);
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $otherZaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    $otherThread = makeOrganiserThread($otherZaak, ['title' => 'Other Municipality Thread']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', ['unread' => 'all'])
        ->assertCanNotSeeTableRecords([$otherThread]);
});

test('thread inbox widget shows both advice and organiser threads', function () {
    $this->advisory->municipalities()->attach($this->municipality);

    $adviceThread = makeAdviceThread($this->zaak, $this->advisory, ['title' => 'Advice Thread']);
    $organiserThread = makeOrganiserThread($this->zaak, ['title' => 'Organiser Thread']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', ['unread' => 'all'])
        ->assertCanSeeTableRecords([$adviceThread, $organiserThread]);
});

test('thread inbox widget can filter by advice thread type', function () {
    $this->advisory->municipalities()->attach($this->municipality);

    $adviceThread = makeAdviceThread($this->zaak, $this->advisory, ['title' => 'Advice Thread']);
    $organiserThread = makeOrganiserThread($this->zaak, ['title' => 'Organiser Thread']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->filterTable('type', ThreadType::Advice)
        ->assertCanSeeTableRecords([$adviceThread])
        ->assertCanNotSeeTableRecords([$organiserThread]);
});

test('thread inbox widget can filter by organiser thread type', function () {
    $this->advisory->municipalities()->attach($this->municipality);

    $adviceThread = makeAdviceThread($this->zaak, $this->advisory, ['title' => 'Advice Thread']);
    $organiserThread = makeOrganiserThread($this->zaak, ['title' => 'Organiser Thread']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', 'all')
        ->filterTable('type', ThreadType::Organiser)
        ->assertCanSeeTableRecords([$organiserThread])
        ->assertCanNotSeeTableRecords([$adviceThread]);
});

test('thread inbox widget has title column', function () {
    livewire(ThreadInboxWidget::class)
        ->assertTableColumnExists('title');
});

test('thread inbox widget has type column', function () {
    livewire(ThreadInboxWidget::class)
        ->assertTableColumnExists('type');
});

test('thread inbox widget has zaak event name column', function () {
    livewire(ThreadInboxWidget::class)
        ->assertTableColumnExists('zaak.reference_data.naam_evenement');
});

test('thread inbox widget has unread filter', function () {
    livewire(ThreadInboxWidget::class)
        ->assertTableFilterExists('unread');
});

test('thread inbox widget has type filter', function () {
    livewire(ThreadInboxWidget::class)
        ->assertTableFilterExists('type');
});

test('thread inbox widget unread filter hides threads without unread messages by default', function () {
    $thread = makeOrganiserThread($this->zaak, ['title' => 'Thread Without Unread']);

    // Default filter is 'unread' — thread with no unread messages should not appear
    livewire(ThreadInboxWidget::class)
        ->assertCanNotSeeTableRecords([$thread]);
});

test('thread inbox widget shows threads when unread filter is set to all', function () {
    $thread = makeOrganiserThread($this->zaak, ['title' => 'Any Thread']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', ['unread' => 'all'])
        ->assertCanSeeTableRecords([$thread]);
});

test('thread inbox widget appears on municipality dashboard', function () {
    livewire(Dashboard::class)
        ->assertOk()
        ->assertSeeLivewire(ThreadInboxWidget::class);
});

test('thread inbox widget is accessible to reviewer user', function () {
    $reviewer = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::Reviewer,
    ]);
    $this->municipality->users()->attach($reviewer);
    $this->actingAs($reviewer);

    $thread = makeOrganiserThread($this->zaak, ['title' => 'Reviewer Visible Thread']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', ['unread' => 'all'])
        ->assertCanSeeTableRecords([$thread]);
});

test('thread inbox widget can search threads by title', function () {
    $targetThread = makeOrganiserThread($this->zaak, ['title' => 'Unique Searchable Title']);
    $otherThread = makeOrganiserThread($this->zaak, ['title' => 'Another Thread Title']);

    livewire(ThreadInboxWidget::class)
        ->filterTable('unread', ['unread' => 'all'])
        ->searchTable('Unique Searchable')
        ->assertCanSeeTableRecords([$targetThread])
        ->assertCanNotSeeTableRecords([$otherThread]);
});
