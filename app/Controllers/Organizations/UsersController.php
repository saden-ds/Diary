<?php

namespace App\Controllers\Organizations;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
use App\Base\DataStore;
use App\Models\OrganizationUser;
use App\Models\User;

class UsersController extends ApplicationController
{
    public function indexAction(): ?View
    {
        $actions = null;
        $organization_user_new_path = null;

        if ($this->current_user->canAdmin($this->current_user->organization_id)) {
            $organization_user_new_path = '/organizations/invites/new';

            $actions[] = [
                'title' => 'Uzaicināt lietotāju',
                'path' => $organization_user_new_path,
                'class_name' => 'js_modal'
            ];
        }

        return View::init('tmpl/organization_users/index.tmpl', [
            'organization_users' => $this->getOrganizationUsers(),
            'organization_invites' => $this->getOrganizationInvites(),
            'organization_user_new_path' => $organization_user_new_path,
            'actions' => $actions
        ]);     
    }

    public function editAction(): ?View
    {
        $data = $this->getOrganizationUser($this->request->get('id'));

        if (empty($data)) {
            throw new NotFoundException();
        }

        if (!$this->current_user->canAdmin($data['organization_id'])) {
            throw new ForbiddenException();
        }

        return View::init('tmpl/organization_users/form.tmpl', [
            'title' => $data['user_firstname'] . ' ' . $data['user_lastname'],
            'role_options' => $this->getRoleOptions($data['organization_user_role']),
            'path' => '/organizations/users/' . intval($data['organization_user_id']) . '/update'
        ]);
    }

    public function updateAction(): ?View
    {
        $data = $this->getOrganizationUser($this->request->get('id'));

        if (empty($data)) {
            throw new NotFoundException();
        }

        if (!$this->current_user->canAdmin($data['organization_id'])) {
            throw new ForbiddenException();
        }

        $view = new View();
        $organization_user = new OrganizationUser($data, true);

        $organization_user->setAttributes($this->request->permit([
            'organization_user_role'
        ]));

        if ($organization_user->update()) {
            return $view->data([
                'organization_user_id' => $organization_user->organization_user_id
            ]);
        } else {
            return $this->recordError($organization_user);
        } 
    }

    public function deleteAction()
    {
        $organization_user = OrganizationUser::find($this->request->get('id'));

        if (empty($organization_user)) {
            throw new ForbiddenException();
        }

        if (!$this->current_user->canAdmin($organization_user->organization_id)) {
            throw new ForbiddenException();
        }

        if (!$organization_user->delete()) {
            $this->flash->error($organization_user->getBaseError());
        }

        return $this->redirect('/organizations/users');
    }

    private function getRoleOptions(?string $role = null): ?array
    {
        $options = null;

        foreach (OrganizationUser::ROLES as $r) {
            $options[] = [
                'name' => $this->msg->t('organization_user.roles.' . $r),
                'value' => $r,
                'selected' => $r === $role
            ];
        }

        return $options;
    }

    private function getOrganizationUsers(): ?array
    {
        $query = new DataQuery();
        $is_admin = $this->current_user->canAdmin($this->current_user->organization_id);

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

        foreach ($data as $k => $v) {
            $user = new User($v, true);

            if ($is_admin && $v['user_id'] !== $v['owner_id']) {
                $v['actions'] = [
                    [
                        'title' => 'Rediģēt',
                        'path' => '/organizations/users/' . intval($v['organization_user_id']) . '/edit',
                        'class_name' => 'js_modal'
                    ],
                    [
                        'title' => 'Dzēst',
                        'path' => '/organizations/users/' . intval($v['organization_user_id']) . '/delete',
                        'class_name' => 'js_confirm_delete menu__anchor_warn'
                    ]
                ];
            }

            $v['user_digit'] = $user->user_digit;
            $v['user_initials'] = $user->user_initials;
            $v['organization_user_role'] = $this->msg->t(
                'organization_user.roles.' . $v['organization_user_role']
            );

            $data[$k] = $v;
        }

        return $data;
    }

    private function getOrganizationInvites(): ?array
    {
        $query = new DataQuery();
        $is_admin = $this->current_user->canAdmin($this->current_user->organization_id);

        $query
            ->select(
                'oi.organization_invite_id',
                'oi.organization_invite_role',
                'oi.organization_invite_email',
                'u.user_id',
                'u.user_email',
                'u.user_firstname',
                'u.user_lastname'
            )
            ->from('organization_invite as oi')
            ->leftJoin('user as u on u.user_email = oi.organization_invite_email')
            ->where('oi.organization_id = ?', $this->current_user->organization_id);

        if (!$data = $query->fetchAll()) {
            return null;
        }
        
        foreach ($data as $k => $v) {
            if ($v['user_id']) {
                $user = new User($v, true);

                $v['user_digit'] = $user->user_digit;
                $v['user_initials'] = $user->user_initials;
            } else {
                $v['user_digit'] = null;
                $v['user_initials'] = null;
            }

            if ($is_admin) {
                $v['actions'] = [
                    [
                        'title' => 'Dzēst',
                        'path' => '/organizations/invites/' . intval($v['organization_invite_id']) . '/delete',
                        'class_name' => 'js_confirm_delete menu__anchor_warn'
                    ]
                ];
            }

            $v['organization_invite_role'] = $this->msg->t(
                'organization_user.roles.' . $v['organization_invite_role']
            );

            $data[$k] = $v;
        }

        return $data;
    }

    private function getOrganizationUser(int $id): ?array
    {
        if (!$id || !$this->current_user->organization_id) {
            return null;
        }

        $query = new DataQuery();

        $query
            ->select(
                'ou.*',
                'u.user_firstname',
                'u.user_lastname'
            )
            ->from('organization_user as ou')
            ->join('user as u on u.user_id = ou.user_id')
            ->where('ou.organization_user_id = ?', $id)
            ->where(
                'ou.organization_id = ?',
                $this->current_user->organization_id
            );

        return $query->fetch();
    }
}