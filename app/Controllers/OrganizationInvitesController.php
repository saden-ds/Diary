<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Models\OrganizationInvite;
use App\Models\OrganizationUser;

class OrganizationInvitesController extends PrivateController
{
    public function acceptAction(): ?View
    {
        $invite = OrganizationInvite::find($this->request->get('invite_id'));

        if (!$invite) {
            throw new NotFoundException();
        }

        if ($invite->organization_invite_email != $this->current_user->email) {
            throw new ForbiddenException();
        }

        $organization_user = new OrganizationUser([
            'organization_user_role' => $invite->organization_invite_role,
            'organization_id' => $invite->organization_id,
            'user_id' => $this->current_user->id
        ]);

        if (!$organization_user->create()) {
            return $this->recordError($organization_user);
        }

        $invite->delete();

        $this->flash->notice('Uzaicinājums veiksmīgi apstiprināts!');

        return $this->redirect('/');
    }

    public function declineAction(): ?View
    {
        $invite = OrganizationInvite::find($this->request->get('invite_id'));

        if (!$invite) {
            throw new NotFoundException();
        }

        if ($invite->organization_invite_email != $this->current_user->email) {
            throw new ForbiddenException();
        }

        $invite->delete();

        $this->flash->notice('Uzaicinājums noraidīts!');

        return $this->redirect('/');
    }
}