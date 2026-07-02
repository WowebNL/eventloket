<?php

namespace App\Models\Users;

use App\Enums\Role;
use App\Models\Traits\ScopesByRole;

/**
 * The koppeling beheerder manages a municipality's ZGW connection, the
 * zaaktype blueprint/mapping and the request logs. It is a municipality-side
 * role with read-only access to that municipality's zaken (it does not handle
 * them).
 */
class KoppelingBeheerderUser extends MunicipalityUser
{
    use ScopesByRole;

    public static function getRole(): Role
    {
        return Role::KoppelingBeheerder;
    }
}
