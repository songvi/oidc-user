<?php
namespace UserFrosting\Sprinkle\OidcUser\Database\Models;

use UserFrosting\Sprinkle\Account\Database\Models\User;
use UserFrosting\Sprinkle\OidcUser\Database\Models\OidcUserAux;
use UserFrosting\Sprinkle\OidcUser\Database\Scopes\OidcUserAuxScope;

trait LinkOidcUserAux
{
    /**
     * The "booting" method of the trait.
     *
     * @return void
     */
    protected static function bootLinkLinkOidcUserAux()
    {
        /**
         * Create a new OIDCUserAux if necessary, and save the associated user data every time.
         */
        static::saved(function ($oidcUser) {
            $oidcUser->createOidcUserIfNotExists();

            if ($oidcUser->auxType) {
                // Set the aux PK, if it hasn't been set yet
                if (!$oidcUser->aux->id) {
                    $oidcUser->aux->id = $oidcUser->id;
                }

                $oidcUser->aux->save();
            }
        });
    }
}

class OidcUser extends User
{
    use LinkOidcUserAux;

    protected $fillable = [
        'user_name',
        'first_name',
        'last_name',
        'email',
        'locale',
        'theme',
        'group_id',
        'flag_verified',
        'flag_enabled',
        'last_activity_id',
        'password',
        'deleted_at',
        'gender',
        'birthdate',
        'zoneinfo',
        'phone_number',
        'address',
        //'roles',
        //'sub',
        'profile'
    ];


    protected $oidcUserType = 'UserFrosting\Sprinkle\OidcUser\Database\Models\OidcUserAux';

    /**
     * Required to be able to access the `oidcUserAux` relationship in Twig without needing to do eager loading.
     * @see http://stackoverflow.com/questions/29514081/cannot-access-eloquent-attributes-on-twig/35908957#35908957
     */
    public function __isset($name)
    {
        if (in_array($name, [
            'oidcUserAux'
        ])) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }

    /**
     * Globally joins the `members` table to access additional properties.
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new OidcUserAuxScope);
    }

    public function setZoneInfoAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->zoneinfo = $value;
    }
    public function setPhoneNumberAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->phone_number = $value;
    }
    public function setAddressAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->address = $value;
    }

    public function setPreferredThemeAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->prefferred_theme = $value;
    }

    public function setPreferredUserNameAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->preferred_username = $value;
    }

    public function setNickNameAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->nickname = $value;
    }

    public function setProfileAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->profile = $value;
    }


    public function setGenderAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->gender = $value;
    }

    public function setBirthDateAttribute($value)
    {
        $this->createOidcUserIfNotExists();

        $this->oidcUserAux->birdthdate = $value;
    }

    /**
     * Relationship for interacting with aux model (`oidc_users` table).
     */
    public function oidcUserAux()
    {
        return $this->hasOne($this->auxType, 'id');
    }

    /**
     * If this instance doesn't already have a related aux model (either in the db on in the current object), then create one
     */
    protected function createOidcUserIfNotExists()
    {
        if ($this->auxType && !count($this->oidcUserAux)) {
            // Create oidc_user model and set primary key to be the same as the main user's
            $oidcUserAux = new $this->auxType;

            // Needed to immediately hydrate the relation.  It will actually get saved in the bootLinkMemberAux method.
            $this->setRelation('oidcUserAux', $oidcUserAux);
        }
    }
}
