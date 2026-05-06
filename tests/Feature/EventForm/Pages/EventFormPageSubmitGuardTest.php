<?php

declare(strict_types=1);

/**
 * Idempotency-guard op EventFormPage::submit(). Achtergrond: de
 * opdrachtgever rapporteerde dat snel dubbel-klikken op "Aanvraag
 * indienen" ofwel niets deed (Filament's button-disabled grijpt al
 * in), ofwel — bij netwerkglitches — soms tóch twee zaken aanmaakte.
 *
 * Onze fix: een `$submitting`-vlag op de Page. De eerste submit-aanroep
 * zet 'm op true; een tweede submit-aanroep binnen dezelfde Livewire-
 * component-lifetime ziet de vlag en doet niets. Een succesvolle
 * submit redirect direct weg, en bij een fout wordt de vlag gereset
 * zodat de organisator kan retry'en.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\Submit\SubmitEventForm;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->state(['role' => Role::Organiser])->create();
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach(
        $this->organisation->id,
        ['role' => OrganisationRole::Admin->value],
    );

    $this->actingAs($this->user);
    Filament::setTenant($this->organisation);
});

/**
 * Stub die het concrete SubmitEventForm vervangt: telt aanroepen en
 * laat de test zelf bepalen wat er gebeurt (zaak teruggeven of falen).
 * SubmitEventForm zelf is `final`, dus we kunnen 'm niet via Mockery
 * mocken — een eigen stub-class is hier de cleanste route. De Laravel-
 * container mag prima een ander type aan `SubmitEventForm::class`
 * koppelen omdat het call-pad het resultaat als `mixed` ziet.
 */
class FakeSubmitEventForm
{
    public int $aantalAanroepen = 0;

    public function __construct(
        public ?Throwable $gooitException = null,
        public mixed $resultaat = null,
    ) {}

    public function execute(...$args): mixed
    {
        $this->aantalAanroepen++;
        if ($this->gooitException) {
            throw $this->gooitException;
        }

        return $this->resultaat;
    }
}

test('twee submit-aanroepen achter elkaar maken maximaal één zaak aan', function () {
    // ZaakObserver dispatcht jobs en verstuurt mails; we hoeven die
    // niet te runnen voor deze guard-test, en factories vereisen
    // anders een complete municipality+zaaktype-keten.
    Bus::fake();
    Notification::fake();

    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'organiser_user_id' => $this->user->id,
        'zaaktype_id' => $zaaktype->id,
    ]);
    $fake = new FakeSubmitEventForm(resultaat: $zaak);
    app()->instance(SubmitEventForm::class, $fake);

    $component = Livewire::test(EventFormPage::class);
    /** @var EventFormPage $page */
    $page = $component->instance();

    // Forceer een geldige user/org-context op de state — mount zet die
    // al, maar we maken 't expliciet voor de leesbaarheid.
    $page->state()->setSystem('authUser', OrganiserUser::find($this->user->id));
    $page->state()->setSystem('authOrganisation', $this->organisation);

    // Eerste submit: SubmitEventForm wordt aangeroepen, page zet
    // submitting=true, redirect naar de zaak-view.
    $component->call('submit');

    // Tweede submit: guard kicked in, SubmitEventForm wordt NIET
    // opnieuw aangeroepen. (In de praktijk landt deze tweede call
    // bijna nooit, want de eerste submit triggert een redirect; we
    // simuleren hier de race waarbij twee POSTs binnenkomen voor
    // de redirect het effect heeft.)
    $component->call('submit');

    expect($fake->aantalAanroepen)->toBe(1);
});

test('na een mislukte submit kan de organisator opnieuw proberen', function () {
    $fake = new FakeSubmitEventForm(
        gooitException: new RuntimeException('OpenZaak onbereikbaar'),
    );
    app()->instance(SubmitEventForm::class, $fake);

    $component = Livewire::test(EventFormPage::class);
    /** @var EventFormPage $page */
    $page = $component->instance();
    $page->state()->setSystem('authUser', OrganiserUser::find($this->user->id));
    $page->state()->setSystem('authOrganisation', $this->organisation);

    // Eerste poging mislukt; guard moet de `submitting`-vlag terugzetten
    // op false zodat een retry-klik wel doorgaat.
    $component->call('submit');
    $component->call('submit');

    expect($fake->aantalAanroepen)->toBe(2);
});
