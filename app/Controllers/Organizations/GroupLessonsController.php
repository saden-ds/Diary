<?php

namespace App\Controllers\Organizations;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
use App\Models\Group;
use App\Models\GroupLesson;

class GroupLessonsController extends ApplicationController
{
    public function newAction(): ?View 
    {
        $group = $this->findGroup();

        return View::init('tmpl/group_lessons/form.tmpl', [
            'group_name' => $group->group_name,
            'lesson_options' => $this->getOrganizationLessonOptions($group),
            'path' => '/groups/' . $group->group_id . '/lessons/create'
        ]);
    }

    public function createAction(): ?View
    {
        $view = new View();
        $group = $this->findGroup();
        $group_lesson = new GroupLesson($this->request->permit([
            'group_id', 'lesson_id'
        ]));

        if ($group_lesson->create()) {
            return $view->data([
                'group_lesson_id' => $group_lesson->group_lesson_id
            ]);
        } else {
            return $this->recordError($group_lesson);
        } 
    }

    public function deleteAction(): ?View
    {
        if ($this->current_user->organization_user_role !== 'admin') {
            throw new ForbiddenException();
        }

        $group = Group::find($this->request->get('group_id'));
        $group_lesson = GroupLesson::find($this->request->get('group_lesson_id'));

        if ($group->group_id != $group_lesson->group_id) {
            throw new ForbiddenException();
        }

        $group_lesson->delete();

        return $this->redirect('/groups/' . $group->group_id);
    }


    private function findGroup(): Group
    {
        $group = Group::find($this->request->get('group_id'));

        if (empty($group)) {
            throw new NotFoundException();
        }

        if (!$this->current_user->canAdmin($group->organization_id)) {
            throw new ForbiddenException();
        }

        return $group;
    }

    private function getOrganizationLessonOptions($group): ?array
    {
        $options = null;
        $query = new DataQuery();

        $query
            ->select('l.lesson_id', 'l.lesson_name')
            ->from('lesson as l')
            ->leftJoin('group_lesson as gl on gl.lesson_id = l.lesson_id and gl.group_id = ?', $group->group_id)
            ->where('l.organization_id = ?', $group->organization_id)
            ->where('gl.group_lesson_id is null');

        $data = $query->fetchAll();

        if (empty($data)) {
            return $options;
        }

        foreach ($data as $r) {
            $options[] = [
                'name' => $r['lesson_name'],
                'value' => $r['lesson_id'],
                'selected' => false
            ];
        }

        return $options;
    }
}