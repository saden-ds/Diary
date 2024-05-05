<?php

namespace App\Controllers;

use App\Base\Exceptions\ForbiddenException;
use App\Base\View;

class PrivateController extends ApplicationController
{
    protected function beforeAction(string $action): void
    {
        if (!$this->current_user->isSignedIn()) {
            throw new ForbiddenException();
        }
    }
}