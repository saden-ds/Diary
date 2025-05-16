<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\Collection;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Base\ParamsCollection;
use App\Base\View;
use App\Models\Assignment;
use App\Models\Schedule;
use App\Services\Assignment\FilesCollection;
use DateTime;
use DateTimeZone;

class AssignmentsController extends PrivateController
{
    public function indexAction(): ?View
    {   
        $view = new View();

        if ($this->request->isXhr()) {
            return View::init(
                'tmpl/assignments/_index.tmpl', 
                $this->getAssignments()
            );
        }

        return $view
            ->path('tmpl/assignments/index.html')
            ->data([
                'filter' => $this->getFilter(),
                'body' => $view->file(
                    'tmpl/assignments/_index.tmpl',
                    $this->getAssignments()
                )
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
        $assignment = Assignment::findAssignmentByIdAndUserId(
            $this->request->get('id'),
            $this->current_user->id
        );

        if (!$assignment) {
            throw new NotFoundException();
        }

        $query = new DataQuery();
        $lesson_name = null;

        $query
            ->select('l.lesson_name')
            ->from('schedule as s')
            ->join('lesson as l on l.lesson_id = s.lesson_id')
            ->where('s.schedule_id = ?', $assignment->schedule_id);

        if ($data = $query->fetch()) {
            $lesson_name = $data['lesson_name'];
        }

        if ($assignment->user_id == $this->current_user->id) {
            return $this->renderShow($assignment, $lesson_name);
        } else {
            return $this->renderUserShow($assignment, $lesson_name);
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

        if ($assignment->assignment_end_datetime) {
            $assignment_end_date = $this->msg->date($assignment->assignment_end_datetime);
            $assignment_end_hour = $this->msg->l($assignment->assignment_end_datetime, ['format' => 'hour']);
            $assignment_end_minute = $this->msg->l($assignment->assignment_end_datetime, ['format' => 'minute']);
        } else {
            $assignment_end_date = null;
            $assignment_end_hour = null;
            $assignment_end_minute = null;
        }

        return View::init('tmpl/assignments/form.html', [
            'assignment_id' => $assignment->assignment_id,
            'assignment_type_option' => $this->getTypeOptions($assignment),
            'assignment_description' => $assignment->assignment_description,
            'assignment_end_date' => $assignment_end_date,
            'assignment_end_hour_options' => $this->getHourOptions($assignment_end_hour),
            'assignment_end_minute_options' => $this->getMinuteOptions($assignment_end_minute),
            'schedule_id' => $assignment->schedule_id,
            'path' => $path
        ]);
    }

    private function getHourOptions($hour): array
    {
        $hour = $hour ?: 12;
        $hours = null;

        for ($i = 0; $i < 24; $i++) {
            $value = $i < 10 ? '0'.$i : $i;
            $hours[] = [
                'name' => $value,
                'value' => $value,
                'selected' => strval($value) === strval($hour)
            ];
        }

        return $hours;
    }

    private function getMinuteOptions($minute): array
    {
        $minute = $minute ?: 0;
        $minutes = null;

        if ($minute % 5 !== 0) {
            $minute = $minute + 5 - $minute % 5;
        }

        for ($i = 0; $i < 12; $i++) {
            $value = $i * 5;
            $value = $value < 10 ? '0' . $value : $value;
            $minutes[] = [
                'name' => $value,
                'value' => $value,
                'selected' => strval($value) === strval($minute)
            ];
        }

        return $minutes;
    }

    private function renderShow(Assignment $assignment, ?string $lesson_name): View
    {
        return View::init('tmpl/assignments/show.tmpl', [
            'lesson_name' => $lesson_name,
            'assignment_id' => $assignment->assignment_id,
            'assignment_type' => $this->msg->t('assignment.types.'.$assignment->assignment_type),
            'assignment_description' => $assignment->assignment_description,
            'assignment_end_datetime' => $this->msg->l($assignment->assignment_end_datetime),
            'assignment_created_at' => $this->msg->l($assignment->assignment_created_at),
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

    private function renderUserShow(Assignment $assignment, ?string $lesson_name): View
    {
        $readonly = $assignment->assignment_end_datetime < gmdate('Y-m-d H:i:s');
        $assignment_file_create_path = null;

        if (!$readonly) {
            $assignment_file_create_path = '/assignments/' . $assignment->assignment_id . '/files/create';
        }

        return View::init('tmpl/assignments/show_user.tmpl', [
            'lesson_name' => $lesson_name,
            'assignment_id' => $assignment->assignment_id,
            'assignment_type' => $this->msg->t('assignment.types.'.$assignment->assignment_type),
            'assignment_description' => $assignment->assignment_description,
            'assignment_end_datetime' => $this->msg->l($assignment->assignment_end_datetime),
            'assignment_created_at' => $this->msg->l($assignment->assignment_created_at),
            'assignment_files' => FilesCollection::renderAssignmentFiles($assignment, [
                'user_id' => $assignment->user_id,
                'current_user_id' => $this->current_user->id
            ]),
            'assignment_user_files' => FilesCollection::renderAssignmentFiles($assignment, [
                'user_id' => $this->current_user->id,
                'current_user_id' => $this->current_user->id,
                'readonly' => $readonly
            ]),
            'assignment_file_create_path' => $assignment_file_create_path
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
        $query = new DataQuery();
        $collection = new Collection([
            'page' => $this->request->get('page', 1),
            'limit' => 40,
            'sort' => $this->request->get('sort'),
            'path' => '/assignments'
        ]);
        $params = new ParamsCollection($this->request->get('filter'));
        $header = [
          $collection->getSortBlock('assignment_end_datetime', 'Termiņš'),
          $collection->getSortBlock('lesson_name', 'Priekšmets'),
          $collection->getSortBlock('assignment_type', 'Apraksts'),
          [
            'title' => 'Vērtējums',
            'path' => null
          ]
        ];
        $items = null;
        $more = null;
        
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
            ->leftJoin(
                'group_user as gu on gu.group_id = gl.group_id' .
                ' and gu.group_id = s.group_id'
            )
            ->where('(
                    l.user_id = ? 
                    or lu.user_id = ?
                    or gu.user_id = ?
                )', [
                $this->current_user->id, 
                $this->current_user->id,
                $this->current_user->id
            ])
            ->limit($collection->limit + 1)
            ->offset($collection->offset)
            ->group('a.assignment_id');

        if ($value = $params->get('lesson_id')) {
          $query->where('l.lesson_id = ?', $value);
        }

        if ($value = $params->get('assignment_owner')) {
            if ($value == 1) {
                $query->where('l.user_id = ?', $this->current_user->id);
            } elseif ($value == 2) {
                $query->where('lu.user_id = ?', $this->current_user->id);
            }
        }

        if ($value = $params->get('assignment_description')) {
            $query->where('a.assignment_description like ?', '%' . $value . '%');
        }

        if ($value = $params->getDate('assignment_end_from')) {
            $query->where('a.assignment_end_datetime >= ?', $value);
        }
        if ($value = $params->getEndOfDate('assignment_end_to')) {
            $query->where('a.assignment_end_datetime <= ?', $value);
        }

        if ($sort_param = $collection->getSortParam()) {
            switch ($sort_param[0]) {
                case 'schedule_date':
                    $query->order('s.schedule_date '.$sort_param[1]);
                    break;
                case 'assignment_end_datetime':
                    $query->order('a.assignment_end_datetime '.$sort_param[1]);
                    break;
                case 'lesson_name':
                    $query->order('l.lesson_name '.$sort_param[1]);
                    break;
                case 'assignment_type':
                    $query->order('a.assignment_type '.$sort_param[1]);
                    break;
                default:
                    $query->order('a.assignment_id desc');
             }
        } 

        $collection->setData($query->fetchAll());

        if (!$collection->isEmpty()) {
            foreach ($collection->data as $key => $value) {
                $assignment_end_count = $this->getAssignmentEndCount($value['assignment_end_datetime']);
                $assignment_days_text = '';

                if ($assignment_end_count < 0) {
                    $assignment_days_text = $this->msg->t('assignment.expired');
                } else {
                    $assignment_days_text = $this->msg->t('assignment.days_left');
                }

                if (abs(intval($assignment_end_count / 3600)) < 1) {
                    $assignment_days_text .= ' ' . $this->msg->t('assignment.minutes', [
                        'count' => abs(intval($assignment_end_count / 60))
                    ]);
                } elseif (abs(intval($assignment_end_count / 86400)) < 1) {
                    $assignment_days_text .= ' ' . $this->msg->t('assignment.hours', [
                        'count' => abs(intval($assignment_end_count / 3600))
                    ]);
                } else {
                    $assignment_days_text .= ' ' . $this->msg->t('assignment.days', [
                        'count' => abs(intval($assignment_end_count / 86400))
                    ]);
                }

                $items[] = [
                    'assignment_row_number' => $key + 1,
                    'assignment_id'=> $value['assignment_id'],
                    'user_fullname' => $value['user_firstname'] . '  ' . $value['user_lastname'],
                    'assignment_description' => $value['assignment_description'],
                    'assignment_type' => $this->msg->t('assignment.types.'.$value['assignment_type']),
                    'assignment_end_datetime' => $this->msg->l($value['assignment_end_datetime']),
                    'schedule_date' => $value['schedule_date'],
                    'lesson_time' => $value['lesson_time_start_at'] . ' - ' . $value['lesson_time_end_at'],
                    'lesson_name' => $value['lesson_name'],
                    'assignment_expired' => $assignment_end_count < 0,
                    'assignment_days_count' => $assignment_days_text
                ];
            }
        }

        if ($collection->hasMore()) {
            $params = $this->request->getQuery();
            $params['page'] = $collection->next_page;
            $more = [[
                'path' => '/assignments?' . http_build_query($params)
            ]];
        }

        return [
            'header' => $header,
            'items' => $items,
            'is_empty' => $collection->isEmpty(),
            'more' => $more,
            'is_wrap' => !$collection->isEmpty() && $collection->page < 2
        ];
    }

    private function getLessonOptions(): ?array
    {
        $query = new DataQuery();

        $query
            ->select('l.lesson_id', 'l.lesson_name')
            ->from('assignment as a')
            ->join('schedule as s on s.schedule_id = a.schedule_id')
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
            ])
            ->group('l.lesson_id')
            ->order('l.lesson_name');

        $options = null;
        $lesson_id = $this->request->get('filter.lesson_id');

        if (!$data = $query->fetchAll()) {
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

    private function getAssignmentEndCount($datetime_string): ?int
    {
        if (!$datetime_string) {
            return null;
        }

        $start_timestamp = time();
        $end_datetime = new DateTime($datetime_string, new DateTimeZone('utc'));

        return $end_datetime->getTimestamp() - $start_timestamp;
    }
}