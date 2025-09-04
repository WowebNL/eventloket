<?php

namespace App\Filament\Admin\Resources\AdminUserResource\Pages;

use App\Filament\Admin\Resources\AdminUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;
}
