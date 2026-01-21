<?php

namespace App\Models\Scopes;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ZaakEventScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && in_array(auth()->user()->role, [Role::Advisor, Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer])) {
            $builder->select('id', 'public_id', 'reference_data', 'zaaktype_id', 'organisation_id', 'organiser_user_id', 'zgw_zaak_url', 'imported_data')->with(['organisation' => function ($query) {
                $query->select('id', 'name', 'type', 'email', 'phone', 'address');
            }, 'zaaktype', 'organiserUser']);

            return;
        }
        $builder->select('id', 'public_id', 'reference_data', 'zaaktype_id', 'organisation_id', 'zgw_zaak_url')->with(['organisation' => function ($query) {
            $query->select('id', 'name', 'type');
        }, 'zaaktype']);
    }
}
