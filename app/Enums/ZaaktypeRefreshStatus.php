<?php

declare(strict_types=1);

namespace App\Enums;

enum ZaaktypeRefreshStatus: string
{
    /** A definitief version was found and the local row reflects it. */
    case Refreshed = 'refreshed';

    /** The catalogus read succeeded but no definitief version exists anymore. */
    case Unavailable = 'unavailable';

    /** The catalogus could not be read (or the sync does not apply); nothing changed. */
    case Failed = 'failed';
}
