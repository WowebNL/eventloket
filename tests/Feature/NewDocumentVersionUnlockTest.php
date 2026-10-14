<?php

declare(strict_types=1);

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Actions\NewDocumentVersionAction;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Exceptions\ValidationException;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->organisation = Organisation::factory()->create(['type' => 'business']);
    $this->organisation->users()->attach($this->reviewer, ['role' => OrganisationRole::Admin]);
    $this->zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);
});

test('a failing patch still releases the document lock and rethrows', function () {
    // Regression (EVENTLOKET-1H): without a guaranteed unlock a failed patch left
    // the document locked, so every later "Nieuwe versie" attempt failed on the
    // lock with a 400 "document is al gelocked" — an error for every user on that
    // document. This test keeps a generic 200 stub out of the setup so the patch
    // genuinely returns a 400.
    Storage::fake('local');
    Storage::put('documents/new-version.pdf', '%PDF-1.4 updated pdf content');

    // Lock succeeds, the patch fails with a ZGW validation error, unlock succeeds.
    Http::fake([
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*/lock' => Http::response(['lock' => 'lock-string-123'], 200),
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*/unlock' => Http::response(null, 204),
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/*' => Http::response([
            'invalidParams' => [['name' => 'inhoud', 'code' => 'invalid', 'reason' => 'rejected']],
        ], 400),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    $this->actingAs($this->reviewer);

    expect(fn () => NewDocumentVersionAction::createNewDocumentVersion('existing-doc-uuid', [
        'titel' => 'Bijgewerkt document',
        'file' => 'documents/new-version.pdf',
        'file_name' => 'new-version.pdf',
    ], $zaak))->toThrow(ValidationException::class);

    // The lock was released despite the failed patch, so the next attempt can lock again.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/enkelvoudiginformatieobjecten/existing-doc-uuid/unlock')
        && $request->method() === 'POST');

    // A failed version does not record an update activity.
    expect(Activity::where('log_name', 'document')->where('event', 'updated')->count())->toBe(0);
});
