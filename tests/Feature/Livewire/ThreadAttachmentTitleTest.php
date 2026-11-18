<?php

declare(strict_types=1);

/**
 * Attaching a new file to a thread message prefills the required title with
 * the name of the uploaded file, without its extension. While the form is
 * open the upload is a Livewire temporary file whose path has a random
 * temporary name, so the title must come from the name the browser sent.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Livewire\Thread\MessageForm;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\OrganiserThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Http::fake([ZgwHttpFake::$baseUrl.'*' => Http::response([], 200)]);
    Storage::fake('local');

    // Creating the thread notifies the municipality; keep that out of the test.
    Notification::fake();

    $this->municipality = Municipality::factory()->create();

    $this->organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin->value]);

    $this->reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->reviewer->municipalities()->attach($this->municipality);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => $this->municipality->id,
            'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ])->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    // The documents come from the cache, so rendering the form makes no ZGW call.
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect());

    $this->thread = OrganiserThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Organiser,
        'created_by' => $this->organiser->id,
        'title' => 'Test thread',
    ]);

    $this->actAs = function (string $role): void {
        if ($role === 'organiser') {
            $this->actingAs($this->organiser);
            Filament::setCurrentPanel(Filament::getPanel('organiser'));
            Filament::setTenant($this->organisation);

            return;
        }

        $this->actingAs($this->reviewer);
        Filament::setCurrentPanel(Filament::getPanel('municipality'));
        Filament::setTenant($this->municipality);
    };
});

test('a new attachment prefills the title with the original file name without its extension', function (string $role) {
    ($this->actAs)($role);

    livewire(MessageForm::class, ['thread' => $this->thread])
        ->mountAction('attach')
        ->fillForm([
            'type' => 'new',
            'file' => UploadedFile::fake()->create('Site plan - version 2.pdf', 12, 'application/pdf'),
        ])
        ->assertActionDataSet(['titel' => 'Site plan - version 2']);
})->with(['organiser', 'reviewer']);

test('a new attachment keeps a title the user already entered', function (string $role) {
    ($this->actAs)($role);

    livewire(MessageForm::class, ['thread' => $this->thread])
        ->mountAction('attach')
        ->fillForm([
            'type' => 'new',
            'titel' => 'My own title',
        ])
        ->fillForm([
            'file' => UploadedFile::fake()->create('Site plan - version 2.pdf', 12, 'application/pdf'),
        ])
        ->assertActionDataSet(['titel' => 'My own title']);
})->with(['organiser', 'reviewer']);
