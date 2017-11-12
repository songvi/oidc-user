<?php
namespace UserFrosting\Sprinkle\OidcUser\Database\Migrations\v400;

use UserFrosting\System\Bakery\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class OidcOauthScopes extends Migration
{
    public $dependencies = [
        //'\UserFrosting\Sprinkle\Account\Database\Migrations\v400\UsersTable'
    ];

    public function up()
    {
        if (!$this->schema->hasTable('oauth_scopes')) {
            $this->schema->create('oauth_scopes', function (Blueprint $table) {
                $table->string('scope', 3070);
                $table->boolean('is_default')->nullable();

                $table->primary('scope');
                $table->unique('scope');

                $table->engine = 'InnoDB';
                $table->collation = 'utf8_unicode_ci';
                $table->charset = 'utf8';
            });
        }
    }

    public function down()
    {
        $this->schema->drop('oauth_scopes');
    }
}
