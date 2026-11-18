<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What happened to the form submission object in the Objects API when the
 * Eventloket data of a zaak was destroyed.
 *
 * Recorded per zaak, so the Eventloket data report states for every zaak
 * whether the object is gone rather than assuming it.
 */
enum DataObjectDestructionStatus: string implements HasColor, HasLabel
{
    /** Deleted now, or already gone (a 404). */
    case Deleted = 'deleted';

    /** The zaak had no form submission object. */
    case NotPresent = 'not_present';

    /** The url did not point at the configured Objects API, so nothing was sent. */
    case Refused = 'refused';

    /** The Objects API was called and the delete failed. */
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __("enums/data_object_destruction_status.{$this->value}.label");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Deleted => 'success',
            self::NotPresent => 'gray',
            self::Refused, self::Failed => 'danger',
        };
    }
}
