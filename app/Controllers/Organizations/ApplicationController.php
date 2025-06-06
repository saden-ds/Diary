<?php

namespace App\Controllers\Organizations;

use App\Controllers\ApplicationController as BaseApplicationController;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;

class ApplicationController extends BaseApplicationController
{
    protected function beforeAction(string $action): void
    {
        if (!$this->current_user->isSignedIn()) {
            $this->redirect('/');
        }

        if (
            !$this->current_user->organization_id
            || !$this->current_user->confirmed
        ) {
            throw new ForbiddenException();
        }
    }
}