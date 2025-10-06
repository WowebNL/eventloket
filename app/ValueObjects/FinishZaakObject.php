<?php

namespace App\ValueObjects;

use App\Models\Users\MunicipalityUser;
use App\Models\Zaak;
use Carbon\Carbon;

final readonly class FinishZaakObject
{
    public ?array $otherParams;

    public function __construct(
        public Zaak $zaak,
        public MunicipalityUser $user,
        public string $resultaattype,
        public string $message_title,
        public string $message_content,
        public ?string $besluittype,
        public ?string $datum_besluit,
        public ?string $ingangsdatum = null,
        public ?string $vervaldatum = null,
        public ?string $besluit_toelichting = null,
        public ?array $besluit_documenten = null,
        public ?string $result_toelichting = null,
        public ?array $message_documenten = null,
        ...$otherParams,
    ) {
        $this->otherParams = $otherParams;
    }

    public function getBesluitData(): ?array
    {
        if ($this->besluittype) {
            return array_filter([
                'verantwoordelijkeOrganisatie' => $this->zaak->openzaak->bronorganisatie,
                'besluittype' => $this->besluittype,
                'zaak' => $this->zaak->openzaak->url,
                'datum' => $this->datum_besluit,
                'ingangsdatum' => $this->ingangsdatum,
                'verzenddatum' => date('Y-m-d'),
                'vervaldatum' => $this->vervaldatum,
                'vervalreden' => $this->vervaldatum ? 'tijdelijk' : null,
                'toelichting' => $this->besluit_toelichting,
            ]);
        }

        return null;
    }

    public function getBesluitDocumenten(): array
    {
        return $this->besluit_documenten ?? [];
    }

    public function getResultaatData(): array
    {
        return array_filter([
            'zaak' => $this->zaak->openzaak->url,
            'resultaattype' => $this->resultaattype,
            'toelichting' => ! $this->besluittype ? $this->result_toelichting : null,
        ]);
    }

    /**
     * Get the data to set the status, only 'statustype' need to set mannualy
     */
    public function getPartialStatusData(): array
    {
        return [
            'zaak' => $this->zaak->openzaak->url,
            'datumStatusGezet' => Carbon::now()->shiftTimezone('UTC')->toAtomString(),
            'statustoelichting' => __('Zaak afgerond via :app', ['app' => config('app.name')]),
            // 'gezetdoor' => $this->user->name, // needs url to betrokkene instead of name
        ];
    }

    public function getMessageData(): array
    {
        return [
            'title' => $this->message_title,
            'content' => $this->message_content,
            'attachments' => $this->message_documenten,
        ];
    }
}
