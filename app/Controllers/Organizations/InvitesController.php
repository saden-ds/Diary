<?php

namespace App\Controllers\Organizations;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Models\OrganizationInvite;
use App\Models\OrganizationUser;

class InvitesController extends ApplicationController
{
    public function newAction(): ?View
    {
        if (!$this->current_user->canAdmin($this->current_user->organization_id)) {
            throw new ForbiddenException();
        }

        return View::init('tmpl/organization_invites/form.tmpl', [
            'role_options' => $this->getRoleOptions(),
            'path' => '/organizations/invites/create'
        ]);
    }

    public function createAction(): ?View
    {
        if (!$this->current_user->canAdmin($this->current_user->organization_id)) {
            throw new ForbiddenException();
        }

        $view = new View();
        $invite = new OrganizationInvite([
            'organization_invite_email' => $this->request->get('organization_invite_email'),
            'organization_invite_role' => $this->request->get('organization_invite_role'),
            'organization_id' => $this->current_user->organization_id,
            'user_id' => $this->current_user->id
        ]);

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
        $invite = OrganizationInvite::find($this->request->get('id'));

        if (empty($invite)) {
            throw new NotFoundException();
        }

        if (!$this->current_user->canAdmin($invite->organization_id)) {
            throw new ForbiddenException();
        }

        $invite->delete();

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
}