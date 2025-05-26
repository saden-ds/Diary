<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\DataStore;
use App\Base\View;
use App\Models\User;

class UsersController extends PrivateController
{
    public function editAction(): ?View
    {
        $user = User::find($this->current_user->id);

        if (!$user) {
            throw new NotFoundException();
        }

        return View::init('tmpl/profile/form.html')
            ->data([
                'user_firstname' => $user->user_firstname,
                'user_lastname' => $user->user_lastname
            ]);
    }


    public function updateAction(): ?View
    {
        $user = User::find($this->current_user->id);
        $view = new View();

        if (!$user->isEqualsPassword($this->request->get('user_password_old'))) {
            $user->addError('user_password_old', $this->msg->t('user.error.password_old'));
        }

        $user->setAttributes($this->request->permit([
            'user_firstname', 'user_lastname', 'user_password', 'user_password_repeat'
        ]));

        if ($user->update()) {
            $this->current_user->update($user);

            return $view->data([
                'user_id' => $user->user_id
            ]);
        } else {
            return $this->recordError($user);
        } 
    }
}