<?php

namespace App\Models\Traits;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;

trait ScopesByRole
{
    /**
     * Boot the trait and add a global scope
     */
    public static function bootScopesByRole()
    {
        static::addGlobalScope('role', function (Builder $builder) {
            $builder->where(self::getRoleKey(), static::getRole());
        });
    }

    /**
     * Get the role for this model.
     * Must be implemented by each user type.
     */
    abstract public static function getRole(): Role;

    public static function getRoleKey(): string
    {
        return 'role';
    }
}
