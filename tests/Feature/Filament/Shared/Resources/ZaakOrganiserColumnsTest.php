<?php

declare(strict_types=1);

/**
 * The zaaktype and reviewer columns are left out of the organiser panel and
 * stay available in the other panels that share the zaken table.
 */

use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Advisor\Resources\Zaken\ZaakResource\Pages\ListAllZaken;
use App\Filament\Organiser\Resources\Zaken\Pages\ListZaken as OrganiserListZaken;
use App\Filament\Organiser\Widgets\OrganiserCalendarWidget;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    ZgwHttpFake::wildcardFake();

    $this->municipality = Municipality::factory()->create();
    $this->organisation = Organisation::factory()->create();

    Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create(['municipality_id' => $this->municipality->id])->id,
        'organisation_id' => $this->organisation->id,
    ]);
});

function actAsOrganiserOf(Organisation $organisation): void
{
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin]);

    test()->actingAs($organiser);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($organisation);
}

it('leaves the zaaktype and reviewer columns out of the organiser list', function () {
    actAsOrganiserOf($this->organisation);

    livewire(OrganiserListZaken::class)
        ->assertOk()
        ->assertTableColumnHidden('zaaktype.name')
        ->assertTableColumnHidden('reviewerUser.name')
        ->assertTableColumnVisible('handledStatusSetByUser.name')
        ->assertDontSeeText(__('resources/zaak.columns.reviewer_user.label'))
        ->assertSeeText(__('resources/zaak.columns.handled_status_set_by_user.label'));
});

it('leaves the zaaktype and reviewer columns out of the organiser calendar list', function () {
    actAsOrganiserOf($this->organisation);

    livewire(OrganiserCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertTableColumnHidden('zaaktype.name')
        ->assertTableColumnHidden('reviewerUser.name')
        ->assertTableColumnVisible('handledStatusSetByUser.name');
});

it('keeps the zaaktype and reviewer columns in the other panels', function (string $panel) {
    Filament::setCurrentPanel(Filament::getPanel($panel));

    $page = match ($panel) {
        'municipality' => function () {
            $coordinator = User::factory()->create(['role' => Role::Coordinator]);
            $coordinator->municipalities()->attach($this->municipality);
            $this->actingAs($coordinator);
            Filament::setTenant($this->municipality);

            return ListZaken::class;
        },
        'advisor' => function () {
            $advisory = Advisory::factory()->create(['can_view_any_zaak' => true]);
            $advisor = User::factory()->create(['role' => Role::Advisor]);
            $advisory->users()->attach($advisor, ['role' => AdvisoryRole::Member]);
            $this->actingAs($advisor);
            Filament::setTenant($advisory);

            return ListAllZaken::class;
        },
        'admin' => function () {
            $this->actingAs(User::factory()->create(['role' => Role::Admin]));

            return ListZaken::class;
        },
    };

    livewire($page())
        ->assertOk()
        ->assertTableColumnVisible('zaaktype.name')
        ->assertTableColumnVisible('reviewerUser.name')
        ->assertTableColumnVisible('handledStatusSetByUser.name');
})->with(['municipality', 'advisor', 'admin']);
