<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Models\Assignment;
use App\Models\Schedule;
use App\Services\Assignment\FilesCollection;

class AssignmentsController extends PrivateController
{
    public function indexAction(): ?View
    {   
        $header = [
          $this->getSortBlock('assignment_end_datetime', 'Termiņš'),
          [
            'title' => 'Priekšmets',
            'path' => null
          ], [
            'title' => 'Apraksts',
            'path' => null
          ], [
            'title' => 'Vērtējums',
            'path' => null
          ]
        ];

        return View::init('tmpl/assignments/index.html')
            ->data([
                'assignments' => $this->getAssignments(),
                'header' => $header,
                'filter' => $this->getFilter()
            ]);
            
    }

    public function newAction(): ?View
    {
        $schedule = Schedule::find($this->request->get('schedule_id'));

        if (!$schedule) {
            throw new NotFoundException();
        }

        $assignment = new Assignment([
            'schedule_id' => $schedule->schedule_id
        ]);

        return $this->renderForm($assignment);    
    }

    public function createAction(): ?View
    {
        $assignment = new Assignment($this->request->permit([
            'assignment_type', 'assignment_description', 'assignment_end_datetime',
            'schedule_id'
        ]));
        
        $view = new View();

        $assignment->user_id = $this->current_user->id;

        if ($assignment->create()) {
            return $view->data([
                'assignment_id' => $assignment->assignment_id
            ]);
        } else {
            return $this->recordError($assignment);
        } 
    }

    public function showAction(): ?View
    {
        $assignment = Assignment::find($this->request->get('id'));

        if (!$assignment) {
            throw new NotFoundException();
        }

        if ($assignment->user_id == $this->current_user->id) {
            return $this->renderShow($assignment);
        } else {
            return $this->renderUserShow($assignment);
        }
    }

    public function editAction(): ?View
    {
        $assignment = Assignment::find($this->request->get('id'));
        
        if ($assignment->user_id != $this->current_user->id) {
            throw new ForbiddenException();
        }

        return $this->renderForm($assignment);
    }

    public function updateAction(): ?View
    {
        $assignment = Assignment::find($this->request->get('id'));
        $view = new View();

        $assignment->setAttributes($this->request->permit([
            'assignment_type', 'assignment_description', 'assignment_end_datetime',
            'schedule_id'
        ]));

        if ($assignment->update()) {
            return $view->data([
                'assignment_id' => $assignment->assignment_id
            ]);
        } else {
            return $this->recordError($assignment);
        }
    }

    public function deleteAction(): ?View
    {
        $assignment = Assignment::find($this->request->get('id'));

        if ($assignment->user_id != $this->current_user->id) {
            throw new ForbiddenException();
        }

        $assignment->delete();

        return $this->redirect('/');
    }

    private function renderForm($assignment): ?View
    {
        $path = null;

        if ($assignment->assignment_id) {  
            $path = '/assignments/' . $assignment->assignment_id . '/update';
        } else {
            $path = '/assignments/create';
        }

        return View::init('tmpl/assignments/form.html', [
            'assignment_id' => $assignment->assignment_id,
            'assignment_type_option' => $this->getTypeOptions($assignment),
            'assignment_description' => $assignment->assignment_description,
            'assignment_end_datetime' => $assignment->assignment_end_datetime,
            'schedule_id' => $assignment->schedule_id,
            'path' => $path
        ]);
    }

    private function renderShow(Assignment $assignment): View
    {
        return View::init('tmpl/assignments/show.tmpl', [
            'assignment_id' => $assignment->assignment_id,
            'assignment_type' => $this->msg->t('assignment.types.'.$assignment->assignment_type),
            'assignment_description' => $assignment->assignment_description,
            'assignment_end_datetime' => $assignment->assignment_end_datetime,
            'assignment_edit_path' => '/assignments/' . $assignment->assignment_id . '/edit',
            'assignment_delete_path' => '/assignments/' . $assignment->assignment_id . '/delete',
            'assignment_grade_path' => '/assignments/' . $assignment->assignment_id . '/grades/new',
            'assignment_files' => FilesCollection::renderAssignmentFiles($assignment, [
                'user_id' => $assignment->user_id,
                'current_user_id' => $this->current_user->id
            ]),
            'assignment_user_files' => FilesCollection::renderAssignmentFiles($assignment, [
                'except_user_id' => $assignment->user_id,
                'current_user_id' => $this->current_user->id
            ]),
            'assignment_file_create_path' => '/assignments/' . $assignment->assignment_id . '/files/create'
        ]);
    }

    private function renderUserShow(Assignment $assignment): View
    {
        return View::init('tmpl/assignments/show_user.tmpl', [
            'assignment_id' => $assignment->assignment_id,
            'assignment_type' => $this->msg->t('assignment.types.'.$assignment->assignment_type),
            'assignment_description' => $assignment->assignment_description,
            'assignment_end_datetime' => $assignment->assignment_end_datetime,
            'assignment_edit_path' => null,
            'assignment_delete_path' => null,
            'assignment_grade_path' => null,
            'assignment_files' => FilesCollection::renderAssignmentFiles($assignment, [
                'user_id' => $assignment->user_id,
                'current_user_id' => $this->current_user->id
            ]),
            'assignment_user_files' => FilesCollection::renderAssignmentFiles($assignment, [
                'user_id' => $this->current_user->id,
                'current_user_id' => $this->current_user->id
            ]),
            'assignment_file_create_path' => '/assignments/' . $assignment->assignment_id . '/files/create'
        ]);
    }

    private function getTypeOptions($assignment): ?array
    {   
        $options = null;

        foreach (Assignment::TYPES as $k) {
            $options[] = [
                'name' => $this->msg->t('assignment.types.'.$k),
                'value' => $k,
                'selected' => $assignment->assignment_type == $k
            ];
        }

        return $options;
    }

    private function getAssignments(): ?array
    {
        $sort_param = $this->getSortParam();
        
        $query = new DataQuery();

        $query
            ->select(
                'a.*',
                'u.user_firstname', 
                'u.user_lastname', 
                's.schedule_date',
                'lt.lesson_time_start_at',
                'lt.lesson_time_end_at',
                'l.lesson_name'
            )
            ->from('assignment as a')
            ->join('user as u on u.user_id = a.user_id')
            ->join('schedule as s on s.schedule_id = a.schedule_id')
            ->join('lesson_time as lt on lt.lesson_time_id = s.lesson_time_id')
            ->join('lesson as l on l.lesson_id = s.lesson_id')
            ->leftJoin('lesson_user as lu on lu.lesson_id = l.lesson_id')
            ->leftJoin('group_lesson as gl on gl.lesson_id = l.lesson_id')
            ->leftJoin('group_user as gu on gu.group_id = gl.group_id')
            ->where('(
                    l.user_id = ? 
                    or lu.user_id = ?
                    or gu.user_id = ?
                )', [
                $this->current_user->id, 
                $this->current_user->id,
                $this->current_user->id
            ]) // --
            ->group('a.assignment_id');

        if ($this->request->get('filter.lesson_id')) {
          $query->where('l.lesson_id = ?', $this->request->get('filter.lesson_id'));
        }

        if ($this->request->get('filter.assignment_owner') == 1) {
            $query->where('l.user_id = ?', $this->current_user->id);
        } elseif ($this->request->get('filter.assignment_owner') == 2) {
            $query->where('lu.user_id = ?', $this->current_user->id);
        }

        if ($this->request->get('filter.assignment_description')) {
          $query->where('a.assignment_description like ?', '%'.$this->request->get('filter.assignment_description').'%');
        }

        switch ($sort_param[0]) {
            case 'schedule_date':
                $query->order('s.schedule_date '.$sort_param[1]);
                break;
            case 'assignment_end_datetime':
                $query->order('a.assignment_end_datetime '.$sort_param[1]);
                break;
         } 

        $data = $query->fetchAll();
        $result = null;

        if (!$data) {
            return $result;
        }

        foreach ($data as $key => $value) {
            $assignment_days_count = $this->getAssignmentDaysCount($value['assignment_end_datetime']);
            $assignment_days_text = '';

            if ($assignment_days_count < 0) {
                $assignment_days_text = $this->msg->t('assignment.expired');
            } else {
                $assignment_days_text = $this->msg->t('assignment.days_left');
            }

            $assignment_days_text .= ' ' . $this->msg->t('assignment.days', [
                'count' => abs($assignment_days_count)
            ]);

            $result[] = [
                'assignment_row_number' => $key + 1,
                'assignment_id'=> $value['assignment_id'],
                'user_fullname' => $value['user_firstname'] . '  ' . $value['user_lastname'],
                'assignment_description' => $value['assignment_description'],
                'assignment_type' => $this->msg->t('assignment.types.'.$value['assignment_type']),
                'assignment_end_datetime' => $this->msg->l($value['assignment_end_datetime']),
                'schedule_date' => $value['schedule_date'],
                'lesson_time' => $value['lesson_time_start_at'] . ' - ' . $value['lesson_time_end_at'],
                'lesson_name' => $value['lesson_name'],
                'assignment_expired' => $assignment_days_count < 0,
                'assignment_days_count' => $assignment_days_text
            ];
        }

        return $result;
    }

    private function getSortParam() 
    {
        $sort_column = null;
        $sort_direction = null;
        $sort_param = $this->request->get('filter.sort');

        if ($sort_param && strpos($sort_param, '.') !== false) {
            list($sort_column, $sort_direction) = explode('.', $sort_param);
        } else {
            $sort_column = $sort_param;
        }

        if ($sort_direction != 'asc') {
            $sort_direction = 'desc';
        }

        return [$sort_column, $sort_direction];
    }

    private function getSortBlock($column, $title) 
    {
        $sort = [$column];
        $sort_param = $this->getSortParam();
        $params = $this->request->get('filter');
        $class_name = 'sort';

        if ($sort_param[0] != $column) {
            $sort[] = 'desc';
        } elseif ($sort_param[1] == 'desc') {
            $class_name .= ' sort_desc';
            $sort[] = 'asc';
        } else {
            $class_name .= ' sort_asc';
            $sort = [];
        }

        $params['sort'] = implode('.', $sort);

        return [
            'title' => $title,
            'path' => '/assignments?'.http_build_query(['filter'=>$params]),
            'class_name' => $class_name
        ];
    }

    private function getLessonOptions(): ?array
    {
        $db = DataStore::init();
        $data = $db->data('
            select 
                l.lesson_id,
                l.lesson_name
            from assignment as a
            join schedule as s on s.schedule_id = a.schedule_id
            join lesson as l on l.lesson_id = s.lesson_id
            left join lesson_user as lu on lu.lesson_id = l.lesson_id
            where l.user_id = ? or lu.user_id = ?
            group by l.lesson_id
            order by l.lesson_name
        ', [
            $this->current_user->id,
            $this->current_user->id
        ]);

        $options = null;
        $lesson_id = $this->request->get('filter.lesson_id');

        if (!$data) {
            return $options;
        }

        foreach ($data as $value) {
            $options[] = [
                'name' => $value['lesson_name'],
                'value' => $value['lesson_id'],
                'selected' => $value['lesson_id'] == $lesson_id
            ];
        }

        return $options;
    }

    private function getUserTypeOptions(): ?array
    {
        $assignment_types = [
            1 => 'Mani uzdevumi',
            2 => 'Uzdotie uzdevumi'
        ];
        $options = null;
        $assignment_owner = $this->request->get('filter.assignment_owner');


        foreach ($assignment_types as $key => $value) {
            $options[] = [
                'name' => $value,
                'value' => $key,
                'selected' => $key == $assignment_owner
            ];
        }

        return $options;
    }

    private function getFilter(): ?array
    {
        return [[
            'lesson_options' => $this->getLessonOptions(),
            'assignment_description' => $this->request->get('filter.assignment_description'),
            'assignment_owner_options' => $this->getUserTypeOptions()
        ]];
    }

    private function getAssignmentDaysCount($datetime_string): ?int
    {
        if (!$datetime_string) {
            return null;
        }

        $start_timestamp = time();
        $end_timestamp = strtotime($datetime_string);

        $diff = $end_timestamp - $start_timestamp;

        return intval($diff / 86400) - 1;
    }
}