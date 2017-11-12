<?php
namespace UserFrosting\Sprinkle\OidcUser\Database\Migrations\v400;

use UserFrosting\System\Bakery\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class OidcOauthClients extends Migration
{
    public $dependencies = [
        //'\UserFrosting\Sprinkle\Account\Database\Migrations\v400\UsersTable'
    ];

    public function up()
    {
        if (!$this->schema->hasTable('oauth_clients')) {
            $this->schema->create('oauth_clients', function (Blueprint $table) {
                $table->string('client_id', 80);
                $table->string('client_secret', 80)->nullable();
                $table->string('redirect_uri', 2000)->nullable();
                $table->string('grant_types', 80)->nullable();
                $table->string('scope', 4000)->nullable();
                $table->string('user_id', 80)->nullable();
                $table->primary('client_id');
                $table->unique('client_id');

                $table->engine = 'InnoDB';
                $table->collation = 'utf8_unicode_ci';
                $table->charset = 'utf8';
            });
        }
    }

    public function down()
    {
        $this->schema->drop('oauth_clients');
    }
}
