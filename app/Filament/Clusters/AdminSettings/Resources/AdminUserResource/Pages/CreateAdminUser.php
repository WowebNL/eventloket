<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdminUserResource\Pages;

use App\Filament\Clusters\AdminSettings\Resources\AdminUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;
}
