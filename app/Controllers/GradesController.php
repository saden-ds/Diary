<?php

namespace App\Controllers;

use App\Base\View;
use App\Base\DataStore;
use DateTime;

class GradesController extends PrivateController
{
    public function indexAction(): ?View
    {
        return View::init('tmpl/grades/index.tmpl');
            
    }
}