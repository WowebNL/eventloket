<?php

namespace App\Models\Users;

use App\Enums\Role;
use App\Models\Traits\ScopesByRole;
use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class AdminUser extends User implements FilamentUser
{
    use ScopesByRole;

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }

    public static function getRole(): Role
    {
        return Role::Admin;
    }
}
