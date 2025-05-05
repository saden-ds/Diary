<?php

namespace App\Controllers;

use App\Base\Exceptions\ForbiddenException;
use App\Base\View;

class SessionsController extends ApplicationController
{   
    public function showAction(): ?View
    {
        if (!$this->current_user->isSignedIn()) {
            throw new ForbiddenException();
        }

        $organizations = $this->current_user->fetchOrganizations();

        return View::init('tmpl/session/show.tmpl', [
            'app_name' => $this->config->get('title'),
            'organizations' => $organizations
        ])->layout('blank');
    }

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

    public function updateAction(): ?View
    {
        if (!$this->current_user->isSignedIn()) {
            throw new ForbiddenException();
        }

        $view = new View();

        if ($this->current_user->selectOrganizationById($this->request->get('organization_id'))) {
            $view->data([
                'organization_id' => $this->current_user->organization_id
            ]);
        } else {
            $view->error($this->msg->t('authorization.message.error.organization'));
        }
        

        $this->redirect('/');

        return null;
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