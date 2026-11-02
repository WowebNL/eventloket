<?php

declare(strict_types=1);

/**
 * A document attached to a message stays referenced by its url, but the thread
 * looks it up in the documents the reader may see. When it is no longer among
 * them (its content expired, it is not or no longer established, or it was
 * removed from the zaak) the thread says so instead of failing to render.
 */

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
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Http::fake([ZgwHttpFake::$baseUrl.'*' => Http::response([], 200)]);

    $this->municipality = Municipality::factory()->create();
    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => $this->municipality->id,
            'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        ])->id,
        'organisation_id' => Organisation::factory()->create()->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);
    $this->documentUrl = ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/1';
});

function threadDocument(string $url, ?string $status, ?bool $inhoudIsVervallen = null): Informatieobject
{
    return new Informatieobject(
        uuid: '1',
        url: $url,
        creatiedatum: now()->toIso8601String(),
        titel: 'Attached document',
        vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
        auteur: 'Test',
        versie: 1,
        bestandsnaam: 'attached.pdf',
        inhoud: 'base64content',
        beschrijving: '',
        informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
        formaat: 'application/pdf',
        locked: false,
        status: $status,
        inhoudIsVervallen: $inhoudIsVervallen,
    );
}

test('a thread document that is no longer visible renders a notice instead of failing', function (array $documenten) {
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect($documenten));

    livewire(Document::class, [
        'zaak' => $this->zaak,
        'documentUrl' => $this->documentUrl,
        'versie' => 1,
    ])
        ->assertOk()
        ->assertSee('Dit document is niet meer beschikbaar')
        ->assertDontSee('attached.pdf');
})->with([
    'definitief, content expired' => fn () => [threadDocument($this->documentUrl, 'definitief', inhoudIsVervallen: true)],
    'in_bewerking' => fn () => [threadDocument($this->documentUrl, 'in_bewerking')],
    'no longer linked to the zaak' => fn () => [],
]);

test('a visible thread document is still shown', function () {
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect([threadDocument($this->documentUrl, 'definitief')]));

    livewire(Document::class, [
        'zaak' => $this->zaak,
        'documentUrl' => $this->documentUrl,
        'versie' => 1,
    ])
        ->assertOk()
        ->assertSee('attached.pdf')
        ->assertDontSee('Dit document is niet meer beschikbaar');
});

test('the message form skips a selected document that is no longer visible', function (?string $status, ?bool $inhoudIsVervallen) {
    Cache::forever("zaak.{$this->zaak->id}.documenten", collect([threadDocument($this->documentUrl, $status, $inhoudIsVervallen)]));

    $thread = AdviceThread::forceCreate([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Thread',
        'advice_status' => AdviceStatus::Concept,
    ]);

    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);
    $this->actingAs($reviewer);

    livewire(MessageForm::class, ['thread' => $thread])
        ->set('documents', [['url' => $this->documentUrl, 'versie' => 1]])
        ->assertOk()
        ->assertDontSee('attached.pdf');
})->with([
    'definitief, content expired' => ['definitief', true],
    'in_bewerking' => ['in_bewerking', null],
]);
