<?php
namespace UserFrosting\Sprinkle\OidcUser\Database\Migrations\v400;

use UserFrosting\System\Bakery\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class OidcOauthAuthorizationCodes extends Migration
{
    public $dependencies = [
        //'\UserFrosting\Sprinkle\Account\Database\Migrations\v400\UsersTable'
    ];

    public function up()
    {
        if (!$this->schema->hasTable('oauth_authorization_codes')) {
            $this->schema->create('oauth_authorization_codes', function (Blueprint $table) {
                $table->string('authorization_code', 40);
                $table->string('client_id', 80);
                $table->string('user_id', 80)->nullable();
                $table->string('redirect_uri', 2000)->nullable();
                $table->timestamp('expires');
                $table->string('id_token', 1000)->nullable();

                $table->primary('authorization_code');
                $table->unique('authorization_code');

                $table->engine = 'InnoDB';
                $table->collation = 'utf8_unicode_ci';
                $table->charset = 'utf8';
            });
        }
    }

    public function down()
    {
        $this->schema->drop('oauth_authorization_codes');
    }
}
