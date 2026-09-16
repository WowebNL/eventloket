<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Livewire\ConnectionVerifier;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use App\Models\Users\MunicipalityUser;

/**
 * Authorises access to a municipality's own ZGW connection (its credentials and
 * endpoints). Mirrors the resource role gate (KoppelingBeheerder and
 * MunicipalityAdmin) and, for any per-record ability, additionally requires the
 * user to belong to the connection's municipality. This is the single source of
 * truth that also guards the non-Filament {@see ConnectionVerifier}
 * path, which has no Filament tenancy to fall back on.
 */
class MunicipalityZgwConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasManagingRole($user);
    }

    public function view(User $user, MunicipalityZgwConnection $connection): bool
    {
        return $this->manages($user, $connection);
    }

    public function create(User $user): bool
    {
        return $this->hasManagingRole($user);
    }

    public function update(User $user, MunicipalityZgwConnection $connection): bool
    {
        return $this->manages($user, $connection);
    }

    public function delete(User $user, MunicipalityZgwConnection $connection): bool
    {
        return $this->manages($user, $connection);
    }

    /**
     * Run the stepped verification flow (connection test, abonnement
     * registration, notification round trip) against this connection.
     */
    public function verify(User $user, MunicipalityZgwConnection $connection): bool
    {
        return $this->manages($user, $connection);
    }

    /**
     * Activate or deactivate this connection (toggle whether it is live and
     * used by the resolver).
     */
    public function activate(User $user, MunicipalityZgwConnection $connection): bool
    {
        return $this->manages($user, $connection);
    }

    public function restore(User $user, MunicipalityZgwConnection $connection): bool
    {
        return false;
    }

    public function forceDelete(User $user, MunicipalityZgwConnection $connection): bool
    {
        return false;
    }

    /**
     * A managing role that also belongs to the connection's municipality.
     */
    private function manages(User $user, MunicipalityZgwConnection $connection): bool
    {
        return $this->hasManagingRole($user)
            && $user instanceof MunicipalityUser
            && $user->canAccessMunicipality($connection->municipality_id);
    }

    private function hasManagingRole(User $user): bool
    {
        return in_array($user->role, [
            Role::KoppelingBeheerder,
            Role::MunicipalityAdmin,
        ], true);
    }
}
