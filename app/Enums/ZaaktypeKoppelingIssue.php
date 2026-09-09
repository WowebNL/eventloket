<?php

declare(strict_types=1);

namespace App\Enums;

enum ZaaktypeKoppelingIssue: string
{
    /** A mapped own-instance zaaktype has no valid definitief version anymore. */
    case Unavailable = 'unavailable';

    /** A previously unavailable zaaktype is valid again and back in use. */
    case Restored = 'restored';

    /** The current version misses blueprint prerequisites (warn-only). */
    case BlueprintIncomplete = 'blueprint_incomplete';

    /** A main-catalogus zaaktype has no valid definitief version anymore. */
    case MainUnavailable = 'main_unavailable';
}
