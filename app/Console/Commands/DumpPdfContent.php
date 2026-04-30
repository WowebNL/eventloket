<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\State\FormState;
use App\Models\Zaak;
use Illuminate\Console\Command;

/**
 * Dumpt de PDF-secties (titel + entries) voor een Zaak als JSON. Wordt
 * door Playwright-tests gebruikt zodat ze de PDF-inhoud kunnen
 * verifiëren zonder een PDF-parser nodig te hebben — de Blade-template
 * is een dunne render bovenop deze data, dus inhoudelijk gelijk.
 *
 * Werkt op de zojuist ingediende zaak via z'n `public_id` (zaaknummer
 * dat de organisator op het scherm ziet) of het primaire UUID.
 */
class DumpPdfContent extends Command
{
    protected $signature = 'eventform:dump-pdf-content {zaak : public_id of UUID van de zaak}';

    protected $description = 'Dump de PDF-inzendingsbewijs-content (sections + entries) als JSON voor een gegeven zaak';

    public function handle(): int
    {
        $identifier = (string) $this->argument('zaak');

        $zaak = Zaak::query()
            ->where('public_id', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (! $zaak instanceof Zaak) {
            $this->error("Geen Zaak gevonden voor '{$identifier}'.");

            return self::FAILURE;
        }

        $snapshot = $zaak->form_state_snapshot;
        if (! is_array($snapshot) || $snapshot === []) {
            $this->error("Zaak '{$identifier}' heeft geen form_state_snapshot.");

            return self::FAILURE;
        }

        $state = FormState::fromSnapshot($snapshot);
        $sections = app(SubmissionReport::class)->build($state, EventFormSchema::stepsForReport());

        // Zaak-meta (zoals in de PDF-header) meeleveren zodat tests ook
        // public_id, zaaktype-naam, organisatie kunnen verifiëren.
        $payload = [
            'zaak' => [
                'public_id' => $zaak->public_id,
                'zaaktype' => $zaak->zaaktype?->name,
                'organisation' => $zaak->organisation?->name,
            ],
            'sections' => $sections,
        ];

        $this->line((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
