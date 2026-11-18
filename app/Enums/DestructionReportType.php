<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Which half of a destruction a report accounts for.
 *
 * The split is on what was destroyed, not on who started it: an Eventloket-data
 * report is written whether the zaak was removed by our own archive module or
 * by a municipality in its own zaaksysteem.
 */
enum DestructionReportType: string implements HasColor, HasLabel
{
    /**
     * The zaakdata in OpenZaak — besluiten, documents, the zaak — destroyed by
     * the archive module from a destruction list. Only ever on our own
     * OpenZaak.
     */
    case Zaakdata = 'zaakdata';

    /**
     * What Eventloket itself held: threads, messages, notifications, the
     * activity log, the form submission object and the zaak row. Generated
     * automatically from the destruction log, for every ZGW connection.
     */
    case EventloketData = 'eventloket_data';

    public function getLabel(): string
    {
        return __("enums/destruction_report_type.{$this->value}.label");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Zaakdata => 'primary',
            self::EventloketData => 'info',
        };
    }
}
