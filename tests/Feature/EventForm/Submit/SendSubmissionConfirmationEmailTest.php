<?php

/**
 * Bevestigingsmail na submit. Dit vervangt OF's `send_confirmation_email`-
 * task. De mail moet:
 *
 *   1. Naar het e-mailadres van de organisator-user gaan.
 *   2. Het zaaknummer (`public_id`) in het onderwerp bevatten zodat de
 *      aanvrager de mail later terugvindt.
 *   3. De eerder gegenereerde PDF als bijlage meenemen.
 *   4. Als de organisator geen e-mail heeft: stil overslaan — niet
 *      crashen, niet proberen in het wilde weg een fallback-adres te
 *      gebruiken.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Jobs\Submit\SendSubmissionConfirmationEmail;
use App\Mail\SubmissionConfirmation;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function zaakMetOrganisator(?string $email = 'noah@example.net'): Zaak
{
    $municipality = Municipality::factory()->create(['name' => 'Heerlen']);
    $zaaktype = Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'municipality_id' => $municipality->id,
        'is_active' => true,
    ]);
    $organisation = Organisation::factory()->create(['name' => 'Media Tuin']);
    /** @var OrganiserUser $user */
    $user = User::factory()
        ->state(['role' => Role::Organiser, 'email' => $email])
        ->create();
    $user->organisations()->attach($organisation, ['role' => OrganisationRole::Admin->value]);

    return Zaak::factory()->create([
        'public_id' => 'ZAAK-2026-0000000001',
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
        'organiser_user_id' => $user->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-06-14T14:00:00+02:00',
            eind_evenement: '2026-06-14T18:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ingediend',
            statustype_url: '',
            naam_evenement: 'Buurtfeest Testlaan',
        ),
    ]);
}

test('stuurt bevestigingsmail naar de organisator met PDF als bijlage', function () {
    Mail::fake();
    Storage::fake('local');

    $zaak = zaakMetOrganisator();

    // Simuleer de PDF die `GenerateSubmissionPdf` eerder zou hebben
    // geschreven — anders is er geen bijlage.
    Storage::disk('local')->put(
        "zaken/{$zaak->id}/aanvraagformulier.pdf",
        '%PDF-1.4 fake content'
    );

    (new SendSubmissionConfirmationEmail($zaak))->handle();

    Mail::assertSent(SubmissionConfirmation::class, function (SubmissionConfirmation $mail) use ($zaak) {
        // Naar de organisator:
        $correctEmpfanger = $mail->hasTo('noah@example.net');

        // Subject bevat het zaaknummer:
        $envelope = $mail->envelope();
        $subjectOk = str_contains($envelope->subject, 'ZAAK-2026-0000000001');

        // Bijlage is aanwezig:
        $bijlagen = $mail->attachments();
        $heeftPdfBijlage = count($bijlagen) === 1;

        return $correctEmpfanger && $subjectOk && $heeftPdfBijlage
            && $mail->zaak->is($zaak);
    });
});

test('zaak zonder gekoppelde organiser-user → mail wordt stil overgeslagen', function () {
    Mail::fake();

    $zaak = zaakMetOrganisator();
    // Simuleer dat de zaak (om welke reden dan ook) geen organiser-user
    // meer heeft. De job moet dan stil blijven, niet crashen of een
    // vreemd fallback-adres gebruiken.
    $zaak->forceFill(['organiser_user_id' => null])->save();
    $zaak = $zaak->fresh(); // loose in-memory relation die nog geladen was

    (new SendSubmissionConfirmationEmail($zaak))->handle();

    Mail::assertNothingSent();
});
