<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;

class JournalsController extends PrivateController
{
    public function indexAction(): ?View
    {   
        return View::init('tmpl/journals/index.tmpl');
    }
}