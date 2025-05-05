<?php

namespace App\Controllers\Organizations;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
use App\Models\ExcusedAbsence;
use App\Models\GroupUser;

class ExcusedAbsenceController extends ApplicationController
{
    public function indexAction(): ?View
    {
        $data = $this->getGroupUserData();
        
        return View::init('tmpl/excused_absences/index.tmpl', [
            'user_firstname' => $data['user_firstname'],
            'user_lastname' => $data['user_lastname'],
            'group_name' => $data['group_name'],
            'excused_absence' => $this->getExcusedAbsenceByGroupUserId($data['group_user_id'])
        ]);
    }

    public function newAction(): ?View
    {
        $data = $this->getGroupUserData();

        if ($data['group_teacher_id'] != $this->current_user->id) {
            throw new ForbiddenException();
        }
        
        return View::init('tmpl/excused_absences/form.tmpl', [
            'user_firstname' => $data['user_firstname'],
            'user_lastname' => $data['user_lastname'],
            'group_name' => $data['group_name'],
            'path' => '/groups/users/' . intval($data['group_user_id']) . '/absences/create'
        ]);
    }

    public function createAction(): ?View
    {
        $data = $this->getGroupUserData();

        if ($data['group_teacher_id'] != $this->current_user->id) {
            throw new ForbiddenException();
        }
        
        $view = new View();
        $absence = new ExcusedAbsence($this->request->permit([
            'excused_absence_from', 'excused_absence_to'
        ]));

        $absence->group_user_id = $data['group_user_id'];
        
        if ($absence->create()) {
            return $view->data([
                'group_user_id' => $absence->group_user_id
            ]);
        } else {
            return $this->recordError($absence);
        } 
    }

    public function deleteAction(): ?View
    {
        $query = new DataQuery();

        $query
            ->select('ea.*', 'g.group_id')
            ->from('excused_absence as ea')
            ->join('group_user as gu on gu.group_user_id = ea.group_user_id')
            ->join('`group` as g on g.group_id = gu.group_id')
            ->join('user as u on u.user_id = gu.user_id')
            ->join('organization_user as ou on ou.organization_user_id = g.organization_user_id')
            ->where('ea.excused_absence_id = ?', $this->request->get('id'))
            ->where('g.organization_id = ?', $this->current_user->organization_id)
            ->where('ou.user_id = ?', $this->current_user->id);

        $data = $query->fetch();

        if (empty($data)) {
            throw new NotFoundException();
        }

        $absence = new ExcusedAbsence($data, true);
        
        $absence->delete();

        return $this->redirect('/groups/' . intval($data['group_id']));
    }


    private function getGroupUserData(): array
    {
        $query = new DataQuery();

        $query
            ->select(
                'gu.group_user_id',
                'u.user_firstname',
                'u.user_lastname',
                'g.group_name',
                'g.organization_id',
                'ou.user_id as group_teacher_id'
            )
            ->from('group_user as gu')
            ->join('`group` as g on g.group_id = gu.group_id')
            ->join('user as u on u.user_id = gu.user_id')
            ->leftJoin('organization_user as ou on ou.organization_user_id = g.organization_user_id')
            ->where('gu.group_user_id = ?', $this->request->get('group_user_id'));

        $data = $query->fetch();

        if (empty($data)) {
            throw new NotFoundException();
        }

        if ($data['organization_id'] !== $this->current_user->organization_id) {
            throw new ForbiddenException();
        }

        return $data;
    }

    private function getExcusedAbsenceByGroupUserId($group_user_id): ?array
    {
        $query = new DataQuery();

        $query
            ->from('excused_absence')
            ->where('group_user_id = ?', $group_user_id)
            ->order('excused_absence_from desc');

        if (!$data = $query->fetchAll()) {
            return null;
        }
        
        foreach ($data as $k => $v) {
            $v['excused_absence_from'] = $this->msg->date($v['excused_absence_from']);
            $v['excused_absence_to'] = $this->msg->date($v['excused_absence_to']);

            $v['actions'] = [[
                'title' => $this->msg->t('action.delete'),
                'path' => '/groups/users/absences/' . intval($v['excused_absence_id']) . '/delete',
                'class_name' => 'menu__anchor_warn'
            ]];

            $data[$k] = $v;
        }


        return $data;
    }
}