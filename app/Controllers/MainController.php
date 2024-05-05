<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\DataStore;
use App\Base\View;

class MainController extends ApplicationController
{
    // public function indexAction(): ?View
    // {

    //     if ($this->current_user->isSignedIn()) {
    //         return $this->renderSchedule();
    //     }

    //     // $tasks = $db->data('
    //     //     select *
    //     //     from tasks
    //     // ');

    //     // $user = $db->row('
    //     //     select *
    //     //     from users
    //     //     limit 1
    //     // ');

    //     return View::init('tmpl/main/index.tmpl')
    //         ->layout('tmpl/blank.tmpl')
    //         ->data([
    //             // 'user_name' => $user['name']
    //         ])
    //         ->meta('description', $this->msg->t('meta.description.main'));
    // }

    
}