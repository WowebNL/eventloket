<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Filament\Admin\Resources\Organisations\Pages\CreateOrganisation;
use App\Filament\Admin\Resources\Organisations\Pages\EditOrganisation;
use App\Filament\Admin\Resources\Organisations\Pages\ListOrganisations;
use App\Filament\Admin\Resources\Organisations\RelationManagers\UsersRelationManager;
use App\Filament\Shared\Actions\OrganiserUser\InviteAction;
use App\Mail\OrganisationInviteMail;
use App\Models\Organisation;
use App\Models\OrganisationInvite;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($admin);

});

test('admin can view organisation resource', function () {
    $organisations = Organisation::factory(['type' => OrganisationType::Business])->count(5)->create();

    livewire(ListOrganisations::class)
        ->assertOk()
        ->assertCanSeeTableRecords($organisations);
});

test('admin can create organisation', function () {
    livewire(CreateOrganisation::class)
        ->fillForm([
            'name' => fake()->company,
            'coc_number' => fake()->numerify('########'),
            'address' => fake()->address,
            'email' => fake()->companyEmail,
            'phone' => fake()->phoneNumber,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

test('admin can edit organisation', function () {
    $record = Organisation::factory(['type' => OrganisationType::Business])->create();
    $formData = [
        'name' => fake()->company,
        'coc_number' => fake()->numerify('########'),
        'address' => fake()->address,
        'email' => fake()->companyEmail,
        'phone' => fake()->phoneNumber,
    ];

    livewire(EditOrganisation::class, [
        'record' => $record->getRouteKey(),
    ])
        ->fillForm($formData)
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Organisation::class, $formData);
});

test('admin can delete organisation', function () {
    $record = Organisation::factory(['type' => OrganisationType::Business])->create();

    livewire(EditOrganisation::class, [
        'record' => $record->getRouteKey(),
    ])
        ->assertOk()
        ->callAction(DeleteAction::class)
        ->assertSuccessful();

    $this->assertModelMissing($record);
});

test('admin can list organisation users on edit organisation page', function () {
    $organisation = Organisation::factory(['type' => OrganisationType::Business])->create();
    $organiser = User::factory(['role' => Role::Organiser])->create();
    $organisation->users()->attach($organiser, ['role' => OrganisationRole::Member]);

    livewire(UsersRelationManager::class, [
        'ownerRecord' => $organisation,
        'pageClass' => EditOrganisation::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($organisation->users);
});

test('admin can invite organisation user for organisation', function () {
    $organisation = Organisation::factory(['type' => OrganisationType::Business])->create();
    $email = fake()->unique()->safeEmail;
    Mail::fake();

    livewire(UsersRelationManager::class, [
        'ownerRecord' => $organisation,
        'pageClass' => EditOrganisation::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make(InviteAction::class)->table(), [
            'name' => fake()->name,
            'email' => $email,
            'makeAdmin' => false,
        ])
        ->assertSuccessful();

    $invite = OrganisationInvite::where('email', $email)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->organisation_id)->toBe($organisation->id)
        ->and($invite->role)->toBe(OrganisationRole::Member->value);

    Mail::assertSent(OrganisationInviteMail::class, function ($mail) use ($email) {
        return $mail->hasTo($email);
    });
});
