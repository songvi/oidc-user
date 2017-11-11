<?php
/**
 * Routes for administrative user management.  Overrides routes defined in routes://admin/users.php
 */
$app->group('/admin/users', function () {
    $this->get('/u/{user_name}', 'UserFrosting\Sprinkle\OidcUser\Controller\OidcUserController:pageInfo');
})->add('authGuard');

$app->group('/api/users', function () {
    $this->put('/u/{user_name}', 'UserFrosting\Sprinkle\OidcUser\Controller\OidcUserController:updateInfo');

})->add('authGuard');