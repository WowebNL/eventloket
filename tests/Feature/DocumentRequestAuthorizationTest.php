<?php

declare(strict_types=1);

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Http::fake([ZgwHttpFake::$baseUrl.'*' => Http::response('pdf-content', 200)]);

    $this->municipality = Municipality::factory()->create();
    $this->otherMunicipality = Municipality::factory()->create();

    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $organisation = Organisation::factory()->create();

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
    ]);

    Cache::forever("zaak.{$this->zaak->id}.documenten", collect([
        new Informatieobject(
            uuid: 'doc-uuid-1',
            url: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-uuid-1',
            creatiedatum: now()->toIso8601String(),
            titel: 'Test Document',
            vertrouwelijkheidaanduiding: DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            auteur: 'Test',
            versie: 1,
            bestandsnaam: 'test.pdf',
            inhoud: ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobject/doc-uuid-1/download',
            beschrijving: 'Test beschrijving',
            informatieobjecttype: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1',
            formaat: 'application/pdf',
            locked: false,
        ),
    ]));
});

// --- Reviewer (municipality-scoped) ---

test('reviewer in same municipality can download document', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->municipality);

    $this->actingAs($reviewer)
        ->get(route('zaak.documents.view', [$this->zaak, 'doc-uuid-1', 'view']))
        ->assertOk();
});

test('reviewer in different municipality is denied document access (IDOR)', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->otherMunicipality);

    $this->actingAs($reviewer)
        ->get(route('zaak.documents.view', [$this->zaak, 'doc-uuid-1', 'view']))
        ->assertForbidden();
});

// --- MunicipalityAdmin (municipality-scoped) ---

test('municipality admin in same municipality can download document', function () {
    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $admin->municipalities()->attach($this->municipality);

    $this->actingAs($admin)
        ->get(route('zaak.documents.view', [$this->zaak, 'doc-uuid-1', 'view']))
        ->assertOk();
});

test('municipality admin in different municipality is denied document access (IDOR)', function () {
    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $admin->municipalities()->attach($this->otherMunicipality);

    $this->actingAs($admin)
        ->get(route('zaak.documents.view', [$this->zaak, 'doc-uuid-1', 'view']))
        ->assertForbidden();
});

// --- Admin ---

test('admin can always download document regardless of municipality', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('zaak.documents.view', [$this->zaak, 'doc-uuid-1', 'view']))
        ->assertOk();
});

// --- Advisor ---

test('advisor with active advice thread can download document', function () {
    $advisory = Advisory::factory()->create(['can_view_any_zaak' => false]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

    AdviceThread::create([
        'zaak_id' => $this->zaak->id,
        'type' => ThreadType::Advice,
        'title' => 'Test advies',
        'advisory_id' => $advisory->id,
        'advice_status' => AdviceStatus::Asked,
    ]);

    $this->actingAs($advisor)
        ->get(route('zaak.documents.view', [$this->zaak, 'doc-uuid-1', 'view']))
        ->assertOk();
});

test('advisor without any thread is denied document access (IDOR)', function () {
    $advisory = Advisory::factory()->create(['can_view_any_zaak' => false]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisor->advisories()->attach($advisory, ['role' => AdvisoryRole::Member]);

    $this->actingAs($advisor)
        ->get(route('zaak.documents.view', [$this->zaak, 'doc-uuid-1', 'view']))
        ->assertForbidden();
});

// --- Unauthenticated ---

test('unauthenticated request is denied document access', function () {
    $this->get(route('zaak.documents.view', [$this->zaak, 'doc-uuid-1', 'view']))
        ->assertForbidden();
});
