<?php

namespace App\Models\Users;

use App\Enums\Role;
use App\Models\Traits\ScopesByRole;

class MunicipalityAdminUser extends MunicipalityUser
{
    use ScopesByRole;

    public static function getRole(): Role
    {
        return Role::MunicipalityAdmin;
    }
}
