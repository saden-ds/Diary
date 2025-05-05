<?php

namespace App\Controllers\Organizations;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
use App\Base\DataStore;
use App\Models\OrganizationInvite;
use App\Models\OrganizationUser;
use App\Models\User;

class InvitesController extends ApplicationController
{
    public function indexAction(): ?View
    {
        $actions = null;
        $organization_user_new_path = null;

        if ($this->current_user->canAdmin()) {
            $organization_user_new_path = '/organizations/users/new';

            $actions[] = [
                'title' => 'Uzaicināt',
                'path' => $organization_user_new_path,
                'class_name' => 'js_modal'
            ];
        }

        return View::init('tmpl/organization_invites/index.tmpl', [
            'organization_users' => $this->getOrganizationUsers(),
            'organization_invites' => $this->getOrganizationInvites(),
            'organization_user_new_path' => $organization_user_new_path,
            'actions' => $actions
        ]);     
    }

    public function newAction(): ?View
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }

        return View::init('tmpl/organization_invites/form.tmpl', [
            'path' => '/organizations/users/create',
            'role_options' => $this->getRoleOptions()
        ]);
    }

    public function createAction(): ?View
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }

        $invite = new OrganizationInvite([
            'organization_invite_email' => $this->request->get('organization_invite_email'),
            'organization_invite_role' => $this->request->get('organization_invite_role'),
            'organization_id' => $this->current_user->organization_id,
            'user_id' => $this->current_user->id
        ]);
        $view = new View();

        if ($invite->organization_invite_email === $this->current_user->email) {
            $invite->addError("base", "Jūs nevarat uzaicināt sevi");
        }

        if ($invite->create()) {
            return $view->data([
                'organization_invite_id' => $invite->organization_invite_id
            ]);
        } else {
            return $this->recordError($invite);
        } 
    }

    public function deleteAction()
    {
        if ($this->current_user->organization_user_role !== 'admin') {
            throw new ForbiddenException();
        }

        $organization_user = OrganizationUser::find($this->request->get('id'));

        if ($organization_user->organization_id != $this->current_user->organization_id) {
            throw new ForbiddenException();
        }

        $organization_user->delete();

        return $this->redirect('/');
    }

    private function getRoleOptions(): ?array
    {
        $options = null;

        foreach (OrganizationUser::ROLES as $r) {
            $options[] = [
                'name' => $this->msg->t('organization_user.roles.' . $r),
                'value' => $r
            ];
        }

        return $options;
    }

    private function getOrganizationUsers(): ?array
    {
        $query = new DataQuery();

        $query
            ->select(
                'ou.*',
                'o.user_id as owner_id',
                'u.user_id',
                'u.user_firstname',
                'u.user_lastname',
                'u.user_email'
            )
            ->from('organization_user as ou')
            ->join('organization as o on o.organization_id = ou.organization_id')
            ->join('user as u on u.user_id = ou.user_id')
            ->where(
                'ou.organization_id = ?',
                $this->current_user->organization_id
            );

        if (!$data = $query->fetchAll()) {
            return null;
        }

        $is_admin = $this->current_user->canAdmin();

        foreach ($data as $k => $v) {
            $user = new User($v, true);

            $v['user_digit'] = $user->user_digit;
            $v['user_initials'] = $user->user_initials;

            if ($is_admin && $v['user_id'] !== $v['owner_id']) {
                $v['actions'] = [
                    [
                        'title' => 'Rediģēt',
                        'path' => '/organizations/users/' . $v['organization_user_id'] . '/edit',
                        'class_name' => 'js_modal'
                    ],
                    [
                        'title' => 'Dzēst',
                        'path' => '/organizations/users/' . $v['organization_user_id'] . '/delete',
                        'class_name' => ''
                    ]
                ];
            }

            $data[$k] = $v;
        }

        return $data;
    }

    private function getOrganizationInvites(): ?array
    {
        $db = DataStore::init();
        $data = $db->data('
            select
                oi.organization_invite_role,
                u.user_id,
                u.user_email,
                u.user_firstname,
                u.user_lastname
            from organization_invite as oi
            left join user as u on u.user_email = oi.organization_invite_email
            where oi.organization_id = ?
        ', [
            $this->current_user->organization_id
        ]);

        if (empty($data)) {
            return null;
        }
        
        foreach ($data as $k => $v) {
            $user = new User($v, true);

            $v['user_digit'] = $user->user_digit;
            $v['user_initials'] = $user->user_initials;

            $data[$k] = $v;
        }

        return $data;
    }
}