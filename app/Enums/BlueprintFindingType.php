<?php

declare(strict_types=1);

namespace App\Enums;

enum BlueprintFindingType: string
{
    /** The zaaktype version has no resource for this slot at all. */
    case Missing = 'missing';

    /** The koppeling names a value that no longer matches any catalogus resource. */
    case MappedValueNotFound = 'mapped_value_not_found';
}
