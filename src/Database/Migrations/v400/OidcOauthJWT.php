<?php
namespace UserFrosting\Sprinkle\OidcUser\Database\Migrations\v400;

use UserFrosting\System\Bakery\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class OidcOauthJWT extends Migration
{
    public $dependencies = [
        //'\UserFrosting\Sprinkle\Account\Database\Migrations\v400\UsersTable'
    ];

    public function up()
    {
        if (!$this->schema->hasTable('oauth_jwt')) {
            $this->schema->create('oauth_jwt', function (Blueprint $table) {
                $table->string('client_id', 80);
                $table->string('subject', 80)->nullable();
                $table->string('public_key', 2000);

                $table->engine = 'InnoDB';
                $table->collation = 'utf8_unicode_ci';
                $table->charset = 'utf8';
            });
        }
    }

    public function down()
    {
        $this->schema->drop('oauth_jwt');
    }
}
