<?php

declare(strict_types=1);

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Actions\NewDocumentVersionAction;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->reviewer, ['role' => OrganisationRole::Admin]);
    $this->zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*/lock' => Http::response(['lock' => 'lock-string-123'], 200),
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*/unlock' => Http::response(null, 204),
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*' => Http::response([], 200),
    ]);
});

test('creating a new document version creates a document activity log entry', function () {
    Storage::fake('local');
    Storage::put('documents/new-version.pdf', '%PDF-1.4 updated pdf content');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $this->actingAs($this->reviewer);

    NewDocumentVersionAction::createNewDocumentVersion('existing-doc-uuid', [
        'titel' => 'Bijgewerkt document',
        'file' => 'documents/new-version.pdf',
        'file_name' => 'new-version.pdf',
    ], $zaak);

    $activity = Activity::where('log_name', 'document')->where('event', 'updated')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Bijgewerkt')
        ->and($activity->causer_id)->toEqual($this->reviewer->id)
        ->and($activity->subject_id)->toEqual($zaak->id)
        ->and($activity->properties->get('document_uuid'))->toBe('existing-doc-uuid')
        ->and($activity->properties->get('filename'))->toBe('new-version.pdf')
        ->and($activity->properties->get('titel'))->toBe('Bijgewerkt document');
});

test('creating a new document version patches the document with the definitief status', function () {
    Storage::fake('local');
    Storage::put('documents/new-version.pdf', '%PDF-1.4 updated pdf content');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $this->actingAs($this->reviewer);

    NewDocumentVersionAction::createNewDocumentVersion('existing-doc-uuid', [
        'titel' => 'Bijgewerkt document',
        'file' => 'documents/new-version.pdf',
        'file_name' => 'new-version.pdf',
    ], $zaak);

    // The new version is pushed as a finalised document, not a draft.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/documenten/api/v1/enkelvoudiginformatieobjecten/existing-doc-uuid')
        && $request->method() === 'PATCH'
        && $request->data()['status'] === 'definitief');
});

test('creating a new document version clears the documenten cache', function () {
    Storage::fake('local');
    Storage::put('documents/new-version.pdf', '%PDF-1.4 updated pdf content');

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    Cache::put("zaak.{$zaak->id}.documenten", collect());
    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeTrue();

    $this->actingAs($this->reviewer);

    NewDocumentVersionAction::createNewDocumentVersion('existing-doc-uuid', [
        'titel' => 'Bijgewerkt document',
        'file' => 'documents/new-version.pdf',
        'file_name' => 'new-version.pdf',
    ], $zaak);

    expect(Cache::has("zaak.{$zaak->id}.documenten"))->toBeFalse();
});
