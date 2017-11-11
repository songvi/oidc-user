<?php
namespace UserFrosting\Sprinkle\OidcUser\Database\Migrations\v400;

use UserFrosting\System\Bakery\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class OidcUserTable extends Migration
{
    public $dependencies = [
        '\UserFrosting\Sprinkle\Account\Database\Migrations\v400\UsersTable'
    ];

    public function up()
    {
        if (!$this->schema->hasTable('oidc_users')) {
            $this->schema->create('oidc_users', function (Blueprint $table) {
                $table->increments('id');
                $table->dateTime('birthdate', 50)->nullable();
                $table->string('zoneinfo', 25)->nullable();
                $table->string('phone_number', 20)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('roles', 700)->nullable();
                $table->string('sub', 255);
                $table->string('profile', 255)->nullable();
                $table->string('gender', 1)->nullable();
                $table->index('sub');
                $table->unique('sub');

                $table->engine = 'InnoDB';
                $table->collation = 'utf8_unicode_ci';
                $table->charset = 'utf8';

                $table->foreign('id')->references('id')->on('users');
            });
        }
    }

    public function down()
    {
        $this->schema->drop('oidc_users');
    }
}
