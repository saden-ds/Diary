<?php

namespace App\Controllers;

use App\Base\View;
use App\Base\DataStore;
use DateTime;

class NotificationsController extends PrivateController
{
    public function latestAction(): ?View
    {
        return View::init('tmpl/notifications/_latest.tmpl');
            
    }
}