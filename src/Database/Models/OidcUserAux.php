<?php

namespace UserFrosting\Sprinkle\OidcUser\Database\Models;

use UserFrosting\Sprinkle\Core\Database\Models\Model;

class OidcUserAux extends Model
{
    public $timestamps = false;

    /**
     * @var string The name of the table for the current model.
     */
    protected $table = 'oidc_users';

    protected $fillable = [
        'gender',
        'birthdate',
        'zoneinfo',
        'phone_number',
        'address',
        //'roles',
        //'sub',
        'profile'
    ];
}
