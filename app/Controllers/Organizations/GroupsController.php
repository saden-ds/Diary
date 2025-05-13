<?php

namespace App\Controllers\Organizations;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Base\Tmpl;
use App\Models\Group;
use App\Models\User;

class GroupsController extends ApplicationController
{
    public function indexAction(): ?View
    {
        if ($this->request->isXhr()) {
            return View::init('tmpl/groups/_index.tmpl', [
                'groups' => $this->getGroups()
            ]);
        }

        $tmpl = Tmpl::init();
        $actions = null;

        if ($this->current_user->canAdmin()) {
            $actions[] = [
                'title' => 'Jauna grupa',
                'path' => '/groups/new',
                'class_name' => 'js_modal'
            ];
        }

        return View::init('tmpl/groups/index.tmpl', [
            'index' => $tmpl->file('tmpl/groups/_index.tmpl', [
                'groups' => $this->getGroups()
            ]),
            'actions' => $actions
        ])->main([
            'compact' => true
        ]);
            
    }

    public function showAction(): ?View
    {
        $group = Group::find($this->request->get('id'));

        if (empty($group)) {
            throw new NotFoundException();
        }

        if ($group->organization_id !== $this->current_user->organization_id) {
            throw new ForbiddenException();
        }

        if ($this->request->isXhr()) {
            return View::init('tmpl/groups/_index.tmpl', [
                'groups' => $this->getGroups($group->group_id)
            ]);
        }

        $tmpl = Tmpl::init();

        $actions = null;

        if ($this->current_user->canAdmin($group->organization_id)) {
            $actions[] = [
                'title' => 'Rediģēt',
                'path' => '/groups/' . $group->group_id . '/edit',
                'class_name' => 'js_modal'
            ];
            $actions[] = [
                'title' => 'Jauns audzēknis',
                'path' => '/groups/' . $group->group_id . '/users/new',
                'class_name' => 'js_modal'
            ];
            $actions[] = [
                'title' => 'Jauns priekšmets',
                'path' => '/groups/' . $group->group_id . '/lessons/new',
                'class_name' => 'js_modal'
            ];
            $actions[] = [
                'title' => 'Lejupielādet lietotājus',
                'path' => '/groups/' . $group->group_id . '/users.xlsx',
                'class_name' => null
            ];
        }

        return View::init('tmpl/groups/show.tmpl', [
            'index' => $tmpl->file('tmpl/groups/_index.tmpl', [
                'groups' => $this->getGroups($group->group_id)
            ]),
            'group_name' => $group->group_name,
            'group_users' => $this->getGroupUsers($group),
            'lessons' => $this->getGroupLessons($group),
            'actions' => $actions
        ])->main([
            'compact' => true
        ]);
    }

    public function newAction(): ?View 
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }

        return $this->renderForm(new Group());
    }

    public function createAction(): ?View
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }

        $group = new Group($this->request->permit([
            'group_name', 'organization_user_id'
        ]));
        $view = new View();

        $group->organization_id = $this->current_user->organization_id;

        if ($group->create()) {
            return $view->data([
                'group_id' => $group->group_id
            ]);
        } else {
            return $this->recordError($group);
        } 
    }

    public function editAction(): ?View 
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }

        $group = Group::find($this->request->get('id'));

        if (empty($group)) {
            throw new NotFoundException();
        }

        if ($group->organization_id !== $this->current_user->organization_id) {
            throw new ForbiddenException();
        }

        return $this->renderForm($group);
    }

    public function updateAction(): ?View 
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }

        $group = Group::find($this->request->get('id'));

        if (empty($group)) {
            throw new NotFoundException();
        }

        if ($group->organization_id !== $this->current_user->organization_id) {
            throw new ForbiddenException();
        }

        $group->setAttributes($this->request->permit([
            'organization_user_id'
        ]));
        $view = new View();

        if ($group->update()) {
            return $view->data([
                'group_id' => $group->group_id
            ]);
        } else {
            return $this->recordError($group);
        } 
    }


    private function renderForm($group): ?View
    {
        return View::init('tmpl/groups/form.tmpl', [
            'group_id' => $group->group_id,
            'group_name' => $group->group_name,
            'organization_user_options' => $this->getOrganizationUserOptions($group),
            'action_path' => $group->group_id
                ? '/groups/' . $group->group_id . '/update'
                : '/groups/create'
        ]);
    }

    private function getOrganizationUserOptions($group): ?array
    {
        $options = null;

        $query = new DataQuery();

        $query
            ->select('ou.organization_user_id', 'u.user_firstname', 'u.user_lastname')
            ->from('organization_user as ou')
            ->join('user u on u.user_id = ou.user_id')
            ->where('ou.organization_id = ?', $this->current_user->organization_id);

        if (!$data = $query->fetchAll()) {
            return $options;
        }

        foreach ($data as $value) {
            $options[] = [
                'name' => $value['user_firstname'] . ' ' . $value['user_lastname'],
                'value' => $value['organization_user_id'],
                'selected' => $value['organization_user_id'] == $group->organization_user_id
            ];
        }

        return $options;
    }

    private function getGroups(?int $group_id = null): ?array
    {
        $query = new DataQuery();

        $query
            ->select('*')
            ->from('`group` as g')
            ->where('g.organization_id = ?', $this->current_user->organization_id);

        if ($value = $this->request->get('q')) {
            $query->where('g.group_name like ?', '%' . $value . '%');
        }

        if (!$data = $query->fetchAll()) {
            return null;
        }

        foreach ($data as $k => $v) {
            $data[$k]['active'] = $v['group_id'] == $group_id;
        }

        return $data;
    }

    private function getGroupUsers($group): ?array
    {
        $query = new DataQuery();

        $query
            ->select(
                'gu.*',
                'ou.user_id as group_teacher_id',
                '(' .
                ' select count(ea.excused_absence_id)' .
                ' from excused_absence as ea' .
                ' where ea.group_user_id = gu.group_user_id' .
                ') as excused_absences_count'
            )
            ->from('group_user as gu')
            ->join('`group` g on g.group_id = gu.group_id')
            ->leftJoin('organization_user as ou on ou.organization_user_id = g.organization_user_id')
            ->where('gu.group_id = ?', $group->group_id);
        
        if (!$data = $query->fetchAll()) {
            return null;
        }

        $is_admin = $this->current_user->canAdmin($group->organization_id);

        foreach ($data as $k => $v) {
            $user = new User([
                'user_fullname' => $v['group_user_name']
            ], true);
            $actions = null;

            $v['status'] = !empty($v['user_id']);
            $v['group_user_digit'] = $user->user_digit;
            $v['group_user_initials'] = $user->user_initials;

            if ($v['group_teacher_id'] == $this->current_user->id) {
                $actions[] = [
                    'title' => 'Pievienot attaisnojuma zīmi',
                    'path' => '/groups/users/' . intval($v['group_user_id']) . '/absences/new',
                    'class_name' => 'js_modal'
                ];
            }

            if ($this->current_user->organization_id) {
                $v['excused_absences_path'] = '/groups/users/' . intval($v['group_user_id']) . '/absences';
            } else {
                $v['excused_absences_path'] = null;
            }

            if ($is_admin) {
                $actions[] = [
                    'title' => 'Dzēst',
                    'path' => '/groups/' . $v['group_id'] . '/users/' . $v['group_user_id'] . '/delete',
                    'class_name' => ''
                ];
            }

            $v['actions'] = $actions;

            $data[$k] = $v;
        }

        return $data;
    }

    private function getGroupLessons($group): ?array
    {
        $query = new DataQuery();

        $query
            ->select('gl.*', 'l.lesson_name', 'u.user_firstname', 'u.user_lastname', 'u.user_email')
            ->from('group_lesson as gl')
            ->join('lesson as l on l.lesson_id = gl.lesson_id')
            ->leftJoin('user as u on u.user_id = l.user_id')
            ->where('gl.group_id = ?', $group->group_id);

        $data = $query->fetchAll();

        $is_admin = $this->current_user->canAdmin($group->organization_id);
        $items = null;

        if ($data) {
            foreach ($data as $r) {
                $user = new User($r, true);

                if ($is_admin) {
                    $actions[] = [
                        'title' => 'Dzēst',
                        'path' => '/groups/' . $r['group_id'] . '/lessons/' . $r['group_lesson_id'] . '/delete',
                        'class_name' => ''
                    ];
                }

                $items[] = [
                    'lesson_name' => $r['lesson_name'],
                    'user_fullname' => $r['user_firstname'] . ' ' . $r['user_lastname'],
                    'user_email' => $r['user_email'],
                    'user_digit' => $user->user_digit,
                    'user_initials' => $user->user_initials,
                    'actions' => $actions
                ];
            }
        }

        return [[
            'items' => $items
        ]];
    }
}