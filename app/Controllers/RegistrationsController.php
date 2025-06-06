<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\DataStore;
use App\Base\View;
use App\Models\User;
use App\Models\UserConfirmation;

class RegistrationsController extends ApplicationController
{
    public function newAction(): ?View
    {
        return View::init('tmpl/registrations/form.tmpl')
            ->layout('blank')
            ->data([
                'error' => null
            ]);
    }

    public function createAction(): ?View
    {
        $view = new View();
        $user = new User($this->request->permit([
            'user_firstname', 'user_lastname', 'user_email',
            'user_password', 'user_password_repeat'
        ]));
        $confirmation = new UserConfirmation();
        
        if ($user->create()) {
            $confirmation->createAndSendMail($user);
            $this->current_user->update($user);
            
            return $view->data([
                'user_id' => $user->user_id
            ]);
        } else {
            return $this->recordError($user);
        }
    }
}