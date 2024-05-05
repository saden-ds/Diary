<?php

namespace App\Controllers;

use App\Base\View;
use App\Base\DataStore;
use DateTime;

class VisitsController extends ApplicationController
{
    public function indexAction(): ?View
    {
        return View::init('tmpl/visits/index.tmpl');
            
    }
}