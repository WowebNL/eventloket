<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\Mail\SubmissionConfirmation;
use App\Models\Zaak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Stuurt de bevestigingsmail naar de organisator. Wordt pas gedispatcht
 * door `GenerateSubmissionPdf` nadat de PDF klaar staat, zodat de mail
 * de PDF als bijlage kan meenemen.
 *
 * Als de organisator geen e-mailadres heeft, slaan we de verzending
 * stil over — OF kreeg ook niet op wonderbaarlijke wijze contact met
 * mensen zonder e-mail; we loggen dit als info voor traceerbaarheid.
 */
final class SendSubmissionConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        $this->zaak->loadMissing(['organiserUser', 'zaaktype.municipality']);

        $user = $this->zaak->organiserUser;
        if (! $user || ! $user->email) {
            Log::info('SendSubmissionConfirmationEmail: geen e-mailadres voor organisator — overgeslagen', [
                'zaak_id' => $this->zaak->id,
                'public_id' => $this->zaak->public_id,
            ]);

            return;
        }

        Mail::to($user->email)->send(new SubmissionConfirmation($this->zaak));
    }
}
