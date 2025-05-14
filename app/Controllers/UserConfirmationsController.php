<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Base\View;
use App\Models\User;
use App\Models\UserConfirmation;

class UserConfirmationsController extends ApplicationController
{
    public function showAction(): ?View
    {
        $view = new View();
        $query = new DataQuery();

        $query
            ->select('u.*', 'uc.user_confirmation_id')
            ->from('user_confirmation uc')
            ->join('user u on u.user_id = uc.user_id')
            ->where('uc.user_confirmation_token = ?', $this->request->get('token'));

        if (!$data = $query->fetch()) {
            return View::init('tmpl/confirmations/show.tmpl')
                ->layout('blank')
                ->data([
                    'is_error' => true
                ]);
        }

        $user = new User($data, true);
        $confirmation = new UserConfirmation($data, true);

        $user->user_confirmed_at = date('Y-m-d H:i:s');

        if ($user->update()) {
            $this->current_user->create($user);
            $this->findAndSetUserGroup($user);
            $confirmation->delete();
        }

        return View::init('tmpl/confirmations/show.tmpl')
            ->layout('blank')
            ->data([
                'is_error' => false
            ]);

        return null;
    }

    public function updateAction(): ?View
    {
        $view = new View();
        $user = User::find($this->current_user->id);

        if (empty($user)) {
            throw new NotFoundException();
        }

        $confirmation = new UserConfirmation();

        if ($confirmation->createAndSendMail($user)) {
            return $view->data([
                'notice' => $this->msg->t('user_confirmation.send_instructions')
            ]);
        } else {
            return $view->error($this->msg->t('user_confirmation.message.error.send'));
        }
    }

    private function findAndSetUserGroup(User $user): void
    {
        $db = DataStore::init();

        $db->query('
            update group_user
            set user_id = ?
            where group_user_email = ?
        ', [
            $user->user_id,
            $user->user_email
        ]);
    }
}