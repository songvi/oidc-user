<?php
namespace UserFrosting\Sprinkle\OidcUser\Database\Migrations\v400;

use UserFrosting\System\Bakery\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class OidcOauthAccessTokens extends Migration
{
    public $dependencies = [
        //'\UserFrosting\Sprinkle\Account\Database\Migrations\v400\UsersTable'
    ];

    public function up()
    {
        if (!$this->schema->hasTable('oauth_access_tokens')) {
            $this->schema->create('oauth_access_tokens', function (Blueprint $table) {
                $table->string('access_token', 40);
                $table->string('client_id', 80);
                $table->string('user_id', 80)->nullable();
                $table->timestamp('expires');
                $table->string('scope', 4000)->nullable();

                $table->primary('access_token');
                $table->unique('access_token');

                $table->engine = 'InnoDB';
                $table->collation = 'utf8_unicode_ci';
                $table->charset = 'utf8';
            });
        }
    }

    public function down()
    {
        $this->schema->drop('oauth_access_tokens');
    }
}
