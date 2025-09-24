<?php

namespace App\Filament\Municipality\Resources\Zaken;

use App\Filament\Shared\Resources\Zaken\ZaakResource as BaseZaakResource;

class ZaakResource extends BaseZaakResource
{
    protected static bool $isDiscovered = true;

    protected static ?string $tenantOwnershipRelationshipName = 'municipality';
}
