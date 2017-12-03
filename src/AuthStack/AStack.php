<?php

namespace UserFrosting\Sprinkle\OidcUser\AuthStack;

use AuthStack\Services\ConfigService;
use AuthStack\AuthStack;

class AStack {
    private $authStack;
    public function construct(){
        $filePath = "../config/auth_stack.yaml";
        $confService = new ConfigService();
        $confService->init($filePath);
        $stack = $confService->getAuthStack();
        $logger = $confService->getLogger();
        $this->authStack = new AuthStack($stack, $logger);
    }

    public function checkPassword($username, $password){
        return $this->authStack->localCheckPassword($username, $password);
    }
}
