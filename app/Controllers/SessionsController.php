<?php

namespace App\Controllers;

use App\Base\Exceptions\ForbiddenException;
use App\Base\View;

class SessionsController extends ApplicationController
{
    public function createAction(): ?View
    {
        if ($this->current_user->isSignedIn()) {
            throw new ForbiddenException();
        }

        $view = new View();

        if ($this->current_user->signIn(
            $this->request->get('email'),
            $this->request->get('password')
        )) {
            return $view->data([
                'email' => $this->current_user->email,
                'firstname' => $this->current_user->firstname,
                'lastname' => $this->current_user->lastname
            ]);
        } elseif ($this->current_user->hasError()) {
            return $view->error($this->current_user->error);
        } else {
            return $view->error($this->msg->t('authorization.message.error.fail'));
        }
    }

    public function deleteAction(): ?View
    {
        $this->current_user->signOut();

        if ($this->request->isJsonRequest()) {
            return View::init([
                'uuid' => $this->current_user->uuid
            ]);
        } else {
            return $this->redirect('/');
        }
    }
}