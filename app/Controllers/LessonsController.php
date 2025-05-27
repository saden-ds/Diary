<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Base\Tmpl;
use App\Models\Lesson;
use App\Models\User;

class LessonsController extends PrivateController
{
    public function indexAction(): ?View
    {
        if ($this->request->isXhr()) {
            return View::init('tmpl/lessons/_index.tmpl', [
                'lessons' => $this->getLessons()
            ]);
        }

        $tmpl = Tmpl::init();
        $actions = null;

        if ($this->current_user->canAdmin($this->current_user->organization_id)) {
            $actions[] = [
                'title' => 'Jauns priekšmets',
                'path' => '/lessons/new',
                'class_name' => 'js_modal'
            ];
        }

        return View::init('tmpl/lessons/index.tmpl', [
            'actions' => $actions,
            'index' => $tmpl->file('tmpl/lessons/_index.tmpl', [
                'lessons' => $this->getLessons()
            ])
        ])->main([
            'compact' => true
        ]);
            
    }

    public function showAction(): ?View
    {
        $lesson = Lesson::find($this->request->get('id'));
        $tmpl = Tmpl::init();

        if (!$lesson) {
            throw new NotFoundException();
        }

        $is_student = $this->isLessonStudent($lesson);
        $can_edit = $this->current_user->canEdit($lesson->user_id, $lesson->organization_id);

        if (!$is_student && !$can_edit) {
            throw new NotFoundException();
        }

        if ($this->request->isXhr()) {
            return View::init('tmpl/lessons/_index.tmpl', [
                'lessons' => $this->getLessons()
            ]);
        }

        $lesson_invites = null;
        $lesson_users = null;
        $actions = null;

        if ($can_edit) {
            $actions[] = [
                'title' => 'Rediģēt priekšmetu',
                'path' => '/lessons/' . $lesson->lesson_id . '/edit',
                'class_name' => 'js_modal'
            ];

            $lesson_invites = $this->getLessonInvites($lesson);
        }

        if ($this->current_user->canAdmin($this->current_user->organization_id)) {
            $actions[] = [
                'title' => 'Jauns priekšmets',
                'path' => '/lessons/new',
                'class_name' => 'js_modal'
            ];
        }

        if (
            empty($lesson->organization_id) &&
            $lesson->user_id == $this->current_user->id
        ) {
            $actions[] = [
                'title' => 'Uzaicināt audzēkni',
                'path' => '/lessons/' . $lesson->lesson_id . '/invites/new',
                'class_name' => 'js_modal'
            ];

            $lesson_users = $this->getLessonUsers($lesson);
        }
        
        return View::init('tmpl/lessons/show.html', [
            'index' => $tmpl->file('tmpl/lessons/_index.tmpl', [
                'lessons'     => $this->getLessons($lesson->lesson_id),
            ]),
            'lesson_id'   => $lesson->lesson_id,
            'lesson_name' => $lesson->lesson_name,
            'lesson_description' => $lesson->lesson_description,
            'lesson_invites' => $lesson_invites,
            'lesson_users' => $lesson_users,
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

        $lesson = new Lesson();

        return $this->renderForm($lesson);    
    }

    public function createAction(): ?View
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }

        $lesson = new Lesson($this->request->permit([
            'lesson_name', 'lesson_description'
        ]));
        $view = new View();

        if ($this->current_user->organization_id) {
            $lesson->user_id = $this->request->get('user_id');
            $lesson->organization_id = $this->current_user->organization_id;
        } else {
            $lesson->user_id = $this->current_user->id;
        }

        if ($lesson->create()) {
            $this->flash->notice('Mācību priekšmets veiksmīgi izveidots!');
            return $view->data([
                'lesson_id' => $lesson->lesson_id
            ]);
        } else {
            return $this->recordError($lesson);
        } 
    }

    public function editAction(): ?View
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }

        $lesson = Lesson::find($this->request->get('id'));

        if (
            $lesson->user_id != $this->current_user->id && 
            !$this->current_user->canAdmin($lesson->organization_id)
        ) {
            throw new ForbiddenException();
        }

        return $this->renderForm($lesson);    
    }

    public function updateAction(): ?View
    {
        if (!$this->current_user->canAdmin()) {
            throw new ForbiddenException();
        }
        
        $lesson = Lesson::find($this->request->get('id'));
        $view = new View();

        if (!$lesson) {
            throw new NotFoundException();
        }

        if (
            $lesson->user_id != $this->current_user->id && 
            !$this->current_user->canAdmin($lesson->organization_id)
        ) {
            throw new ForbiddenException();
        }

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
        $query = new DataQuery();
        $query
            ->select(
                'l.lesson_id',
                'l.lesson_name',
                'l.user_id',
                'concat(u.user_firstname, " ", u.user_lastname) as user_fullname',
                'o.organization_name'
            )
            ->from('lesson as l')
            ->join('user as u on u.user_id = l.user_id')
            ->order('l.lesson_name');

        if ($value = $this->request->get('q')) {
            $query->where('l.lesson_name like ?', '%' . $value . '%');
        }

        if ($this->current_user->organization_id) {
           $query
                ->join('organization o on o.organization_id = l.organization_id')
                ->join('organization_user ou on ou.organization_id = l.organization_id')
                ->where('l.organization_id = ?', $this->current_user->organization_id)
                ->where('ou.user_id = ?', $this->current_user->id)
                ->where('(l.user_id = ou.user_id or organization_user_role = ?)', 'admin');
        } else {
            $query
                ->leftJoin('organization o on o.organization_id = l.organization_id')
                ->leftJoin('group_lesson gl on gl.lesson_id = l.lesson_id')
                ->leftJoin(
                    'group_user gu on gu.group_id = gl.group_id' .
                    ' and gu.user_id = ?',
                    $this->current_user->id
                )
                ->leftJoin('lesson_user as lu on lu.lesson_id = l.lesson_id')
                ->where('(l.user_id = ? or ifnull(gu.user_id,lu.user_id) = ?)', [
                    $this->current_user->id,
                    $this->current_user->id
                ])
                ->group('l.lesson_id');
        }

        if (!$data = $query->fetchAll()) {
            return null;
        }

        $lessons = null;

        foreach ($data as $r) {
            $user_fullname = null;
            $lesson_type = null;
            
            if ($r['user_id'] != $this->current_user->id) {
                $user_fullname = $r['user_fullname'];
            }

            if (!$this->current_user->organization_id && $r['user_id'] != $this->current_user->id) {
                $lesson_type = 'student';
            } else {
                $lesson_type = 'teacher';
            }

            $lessons[] = [
                'lesson_name' => $r['lesson_name'],
                'lesson_path' => '/lessons/' . intval($r['lesson_id']),
                'lesson_type' => $lesson_type,
                'user_fullname' => $user_fullname,
                'organization_name' => $r['organization_name'] ?: 'Privāts',
                'active' => $r['lesson_id'] == $id
            ];
        }

        return $lessons;
    }

    private function renderForm($lesson): ?View
    {
        $path = null;

        if ($lesson->lesson_id) { 
            $title = 'Rediģēt priekšmetu';
            $path = '/lessons/' . $lesson->lesson_id . '/update';
        } else {
            $title = 'Jauns priekšmets';
            $path = '/lessons/create';
        }

        return View::init('tmpl/lessons/form.tmpl', [
            'title' => $title,
            'lesson_name' => $lesson->lesson_name,
            'lesson_description' => $lesson->lesson_description,
            'organization_users_options' => $this->getLessonOrganizationUsersOptions($lesson),
            'path' => $path
        ]);
    }

    private function getLessonInvites($lesson): ?array
    {
        $db = DataStore::init();
        $data = $db->data('
            select
                li.lesson_invite_id,
                u.user_id,
                u.user_email,
                u.user_firstname,
                u.user_lastname,
                l.user_id as owner_id
            from lesson_invite as li
            left join user as u on u.user_email = li.lesson_invite_email
            join lesson as l on l.lesson_id = li.lesson_id
            where li.lesson_id = ? 
                and l.user_id = ?
        ', [
            $lesson->lesson_id,
            $this->current_user->id
        ]);

        if (empty($data)) {
            return null;
        }
        
        foreach ($data as $k => $v) {
            $user = new User($v, true);

            if ($v['owner_id'] == $this->current_user->id) {
                $v['actions'] = [[
                    'title' => $this->msg->t('action.delete'),
                    'path' => '/lessons/invites/' . intval($v['lesson_invite_id']) . '/delete',
                    'class_name' => 'js_confirm_delete menu__anchor_warn'
                ]];
            } else {
                $v['actions'] = null;
            }

            $v['user_digit'] = $user->user_digit;
            $v['user_initials'] = $user->user_initials;

            $data[$k] = $v;
        }

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

        if (empty($data)) {
            return null;
        }
        
        foreach ($data as $k => $v) {
            $user = new User($v, true);

            if ($v['owner_id'] == $this->current_user->id) {
                $v['actions'] = [[
                    'title' => $this->msg->t('action.delete'),
                    'path' => '/lessons/users/' . intval($v['lesson_user_id']) . '/delete',
                    'class_name' => 'js_confirm_delete menu__anchor_warn'
                ]];
            } else {
                $v['actions'] = null;
            }

            $v['user_digit'] = $user->user_digit;
            $v['user_initials'] = $user->user_initials;

            $data[$k] = $v;
        }

        return $data;
    }

    private function getLessonOrganizationUsersOptions(Lesson $lesson): ?array
    {
        $options = null;

        if (empty($this->current_user->organization_id)) {
            return $options;
        }

        $query = new DataQuery();

        $query
            ->select('ou.user_id', 'u.user_firstname', 'u.user_lastname')
            ->from('organization_user as ou')
            ->leftJoin('user as u on u.user_id = ou.user_id')
            ->where('ou.organization_id = ?', $this->current_user->organization_id)
            ->where('ou.organization_user_role = ?', 'teacher');

        $data = $query->fetchAll();

        if (empty($data)) {
            return $options;
        }

        foreach ($data as $r) {
            $options[] = [
                'name' => $r['user_firstname'] . ' ' . $r['user_lastname'],
                'value' => $r['user_id'],
                'selected' => $r['user_id'] = $lesson->user_id
            ];
        }

        return $options;

    }

    private function isLessonStudent(Lesson $lesson): bool
    {
        if (
            $this->current_user->organization_id ||
            $this->current_user->id == $lesson->user_id
        ) {
            return false;
        }

        $query = new DataQuery();

        $query
            ->select('1 as one')
            ->from('lesson l')
            ->leftJoin('group_lesson gl on gl.lesson_id = l.lesson_id')
            ->leftJoin(
                'group_user gu on gu.group_id = gl.group_id' .
                ' and gu.user_id = ?',
                $this->current_user->id
            )
            ->leftJoin('lesson_user as lu on lu.lesson_id = l.lesson_id')
            ->where('ifnull(gu.user_id,lu.user_id) = ?', $this->current_user->id);

        return !!$query->first();
    }

}