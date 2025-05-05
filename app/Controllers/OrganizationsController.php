<?php

namespace App\Controllers;

use App\Base\View;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Models\Organization;
use App\Models\OrganizationUser;

class OrganizationsController extends ApplicationController
{
    public function newAction(): ?View
    {
        return View::init('tmpl/organizations/form.tmpl')
            ->layout('blank');
    }

    public function createAction(): ?View
    {
        $view = new View();
        $organization = new Organization($this->request->permit([
            'organization_name'
        ]));

        $organization->user_id = $this->current_user->id;
        
        if ($organization->create()) {
            $user = new OrganizationUser([
                'organization_user_role' => 'admin',
                'organization_id' => $organization->organization_id,
                'user_id' => $this->current_user->id
            ]);

            if ($user->create()) {
                $this->current_user->selectOrganizationById($organization->organization_id);
            }
            
            return $view->data([
                'organization_id' => $organization->organization_id
            ]);
        } else {
            return $this->recordError($organization);
        }


    }
}