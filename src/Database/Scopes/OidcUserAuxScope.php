<?php

namespace UserFrosting\Sprinkle\OidcUser\Database\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class OidcUserAuxScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $baseTable = $model->getTable();
        // Hardcode the table name here, or you can access it using the classMapper and `getTable`
        $oidcUserTable = 'oidc_users';

        // Specify columns to load from base table and aux table
        $builder->addSelect(
            "$baseTable.*",
            "$oidcUserTable.gender as gender",
            "$oidcUserTable.birthdate as birthdate",
            "$oidcUserTable.zoneinfo as zoneinfo",
            "$oidcUserTable.phone_number as phone_number",
            "$oidcUserTable.address as address",
            "$oidcUserTable.roles as roles",
            "$oidcUserTable.sub as sub",
            "$oidcUserTable.profile as profile",
            "$oidcUserTable.idp as idp"
        );

        // Join on matching `oidc_user` records
        $builder->leftJoin($oidcUserTable, function ($join) use ($baseTable, $oidcUserTable) {
            $join->on("$oidcUserTable.id", '=', "$baseTable.id");
        });
    }
}
