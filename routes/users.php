<?php
/**
 * Routes for administrative user management.  Overrides routes defined in routes://admin/users.php
 */
$app->group('/admin/users', function () {
    $this->get('/u/{user_name}', 'UserFrosting\Sprinkle\OidcUser\Controller\OidcUserController:pageInfo');
})->add('authGuard');

$app->group('/api/users', function () {
    $this->put('/u/{user_name}', 'UserFrosting\Sprinkle\OidcUser\Controller\OidcUserController:updateInfo');
    $this->post('', 'UserFrosting\Sprinkle\OidcUser\Controller\OidcUserController:create');
})->add('authGuard');

$app->group('/account', function () {
    $this->post('/register', 'UserFrosting\Sprinkle\OidcUser\Controller\OidcAccountController:register');
    $this->post('/settings/profile', 'UserFrosting\Sprinkle\OidcUser\Controller\OidcAccountController:profile')
        ->add('authGuard');
});

$app->group('/oauth2', function () {
    $this->post('/token', 'UserFrosting\Sprinkle\OidcUser\Controller\Token:token');
    //$this->post('/authorize', 'UserFrosting\Sprinkle\OidcUser\Oauth2\Authorize:authorizeFormSubmit');
    $this->get('/authorize', 'UserFrosting\Sprinkle\OidcUser\Oauth2\Authorize:authorize')->add('authGuard');
});