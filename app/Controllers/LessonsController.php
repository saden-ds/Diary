<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\View;
use App\Base\DataStore;
use App\Models\Lesson;

class LessonsController extends PrivateController
{
    public function indexAction(): ?View
    {
        return View::init('tmpl/lessons/index.tmpl', [
            'lessons' => $this->getLessons()
        ]);
            
    }

    public function showAction(): ?View
    {
        $lesson = Lesson::find($this->request->get('id'));

        if (!$lesson) {
            throw new NotFoundException();
        }

        $actions = null;

        if ($lesson->user_id == $this->current_user->id) {
            $actions[] = [
                'title' => 'Rediģēt',
                'path' => '/lessons/' . $lesson->lesson_id . '/edit',
                'class_name' => 'js_modal'
            ];
            $actions[] = [
                'title' => 'Uzaicināt',
                'path' => '/lessons/' . $lesson->lesson_id . '/invites/new',
                'class_name' => 'js_modal'
            ];
        }
        
        return View::init('tmpl/lessons/show.html', [
            'lessons'     => $this->getLessons($lesson->lesson_id),
            'lesson_id'   => $lesson->lesson_id,
            'lesson_name' => $lesson->lesson_name,
            'lesson_description' => $lesson->lesson_description,
            'lesson_invites' => $this->getLessonInvites($lesson),
            'lesson_users' => $this->getLessonUsers($lesson),
            'actions' => $actions
        ]);
            
    }

    public function newAction(): ?View
    {
        $lesson = new Lesson();

        return $this->renderForm($lesson);    
    }

    public function createAction(): ?View
    {
        $lesson = new Lesson($this->request->permit([
            'lesson_name', 'lesson_description'
        ]));
        $view = new View();

        $lesson->user_id = $this->current_user->id;

        if ($lesson->create()) {
            return $view->data([
                'lesson_id' => $lesson->lesson_id
            ]);
        } else {
            return $this->recordError($lesson);
        } 
    }

    public function editAction(): ?View
    {
        $lesson = Lesson::find($this->request->get('id'));

        if ($lesson->user_id != $this->current_user->id) {
            throw new ForbiddenException();
        }

        return $this->renderForm($lesson);    
    }

    public function updateAction(): ?View
    {
        $lesson = Lesson::find($this->request->get('id'));
        $view = new View();

        $lesson->setAttributes($this->request->permit([
            'lesson_name', 'lesson_description'
        ]));

        if ($lesson->update()) {
            return $view->data([
                'lesson_id' => $lesson->lesson_id
            ]);
        } else {
            return $this->recordError($lesson);
        } 
    }


    private function getLessons(?int $id = null): ?array
    {
        $db = DataStore::init();
        $data = $db->data('
            select
                l.lesson_id,
                l.lesson_name
            from lesson as l
            join user as u on u.user_id = l.user_id
            where u.user_id = ?
        ', [
            $this->current_user->id
        ]);

        if (!$data) {
            return null;
        }

        $lessons = null;

        foreach ($data as $r) {
            $lessons[] = [
                'lesson_name' => $r['lesson_name'],
                'lesson_path' => '/lessons/' . intval($r['lesson_id']),
                'active'      => $r['lesson_id'] == $id
            ];
        }

        return $lessons;
    }

    private function renderForm($lesson): ?View
    {
        $path = null;

        if ($lesson->lesson_id) {  
            $path = '/lessons/' . $lesson->lesson_id . '/update';
        } else {
            $path = '/lessons/create';
        }

        return View::init('tmpl/lessons/form.tmpl', [
            'lesson_name' => $lesson->lesson_name,
            'lesson_description' => $lesson->lesson_description,
            'path' => $path
        ]);
    }

    private function getLessonInvites($lesson): ?array
    {
        $db = DataStore::init();
        $data = $db->data('
            select
                li.user_email,
                u.user_id,
                u.user_firstname,
                u.user_lastname
            from lesson_invite as li
            left join user as u on u.user_email = li.user_email
            join lesson as l on l.lesson_id = li.lesson_id
            where li.lesson_id = ? 
                and l.user_id = ?
        ', [
            $lesson->lesson_id,
            $this->current_user->id
        ]);

        return $data;
    }


    private function getLessonUsers($lesson): ?array
    {
        $db = DataStore::init();
        $data = $db->data('
            select
                lu.lesson_user_id,
                lu.user_id,
                u.user_firstname,
                u.user_lastname,
                u.user_email,
                l.user_id as owner_id
            from lesson_user as lu
            left join user as u on u.user_id = lu.user_id
            join lesson as l on l.lesson_id = lu.lesson_id
            where lu.lesson_id = ?
        ', [
            $lesson->lesson_id
        ]);

        foreach ($data as $key => $value) {
            if ($value['owner_id'] == $this->current_user->id) {
                $data[$key]['lesson_user_delete_path'] = '/lessons/users/' . intval($value['lesson_user_id']) . '/delete';
            } else {
                $data[$key]['lesson_user_delete_path'] = null;
            }

        }

        return $data;
    }

}



