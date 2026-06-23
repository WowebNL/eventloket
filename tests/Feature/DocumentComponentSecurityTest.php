<?php

declare(strict_types=1);

use App\Enums\AdviceStatus;
use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Livewire\Thread\Document;
use App\Livewire\Thread\MessageForm;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Locked;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Http::fake([ZgwHttpFake::$baseUrl.'*' => Http::response([], 200)]);
});

// Helper that creates an Informatieobject value object matching ZgwHttpFake defaults.
function makeDocument(string $url, int $versie = 1): Informatieobject
{
    return new Informatieobject(
        uuid: '1',
        url: $url,
        creatiedatum: now()->toIso8601String(),
        titel: 'Test Document',
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        auteur: 'Test',
        versie: $versie,
        bestandsnaam: 'test.pdf',
        inhoud: 'base64content',
        beschrijving: 'Test beschrijving',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
    );
}

test('Document component has #[Locked] on all IDOR-sensitive properties', function () {
    $reflection = new ReflectionClass(Document::class);

    foreach (['zaak', 'documentUrl', 'versie', 'latestVersion'] as $property) {
        $attributes = $reflection->getProperty($property)->getAttributes(Locked::class);

        expect($attributes)->not->toBeEmpty(
            "Property \${$property} must carry #[Locked] to prevent IDOR via Livewire payload injection"
        );
    }
});

test('Document component mounts correctly with valid zaak and document', function () {
    $documentUrl = ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/1';

    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    Cache::forever("zaak.{$zaak->id}.documenten", collect([makeDocument($documentUrl)]));

    livewire(Document::class, [
        'zaak' => $zaak,
        'documentUrl' => $documentUrl,
        'versie' => 1,
    ])->assertOk();
});

// VUL-05 ——————————————————————————————————————————————————————————————————

test('MessageForm component has #[Locked] on $thread to prevent IDOR via payload injection', function () {
    $attributes = (new ReflectionClass(MessageForm::class))
        ->getProperty('thread')
        ->getAttributes(Locked::class);

    expect($attributes)->not->toBeEmpty(
        'Property $thread must carry #[Locked] to prevent thread-swapping attacks'
    );
});

test('MessageForm component mounts correctly with a valid thread', function () {
    $municipality = Municipality::factory()->create();
    $organisation = Organisation::factory()->create();
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
    ]);

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test thread',
        'advice_status' => AdviceStatus::Concept,
    ]);

    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $municipality->users()->attach($reviewer);

    $this->actingAs($reviewer);

    livewire(MessageForm::class, ['thread' => $thread])
        ->assertOk();
});
