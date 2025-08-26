<?php

namespace App\Models\Users;

use App\Enums\Role;
use App\Models\Traits\ScopesByRole;

class ReviewerMunicipalityAdminUser extends MunicipalityAdminUser
{
    use ScopesByRole;

    public static function getRole(): Role
    {
        return Role::ReviewerMunicipalityAdmin;
    }
}
