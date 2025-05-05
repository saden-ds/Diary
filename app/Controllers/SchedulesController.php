<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
use App\Base\DataStore;
use App\Models\Schedule;
use App\Models\Lesson;

class SchedulesController extends ApplicationController
{


    public function indexAction(): ?View
    {

        if ($this->current_user->isSignedIn()) {
            return $this->renderSchedule();
        }

        return View::init('tmpl/main/index.tmpl')
            ->layout('blank')
            ->data([
                'app_name' => $this->config->get('title')
            ])
            ->meta('description', $this->msg->t('meta.description.main'));
    }


	public function newAction(): ?View
    {
        $schedule = new Schedule();

        return $this->renderForm($schedule);    
    }

    public function showAction(): ?View
    {
        $schedule = Schedule::find($this->request->get('id'));

        if (!$schedule) {
            throw new NotFoundException();
        }

        return View::init('tmpl/schedules/show.html', [
           'assignments' => $this->getAssignments($schedule)
        ]);
    }

    public function createAction(): ?View
    {
        $schedule = new Schedule($this->request->permit([
            'schedule_name', 'schedule_date', 'lesson_id', 'lesson_time_id'
        ]));
        $view = new View();

        if (!$this->current_user->organization_id) {
            $schedule->schedule_active = true;
        }

        if ($schedule->create()) {
            return $view->data([
                'schedule_id' => $schedule->schedule_id
            ]);
        } else {
            return $this->recordError($schedule);
        } 
    }

    public function editAction(): ?View
    {
        $schedule = Schedule::find($this->request->get('id'));

        if (!$schedule) {
            throw new NotFoundException();
        }

        $lesson = Lesson::find($schedule->lesson_id);

        if ($lesson->user_id != $this->current_user->id) {
            throw new ForbiddenException();
        }

        return $this->renderForm($schedule);
    }

    public function updateAction(): ?View
    {
        $schedule = Schedule::find($this->request->get('id'));
        $view = new View();

        $schedule->setAttributes($this->request->permit([
            'schedule_name', 'schedule_date', 'lesson_id', 'lesson_time_id'
        ]));

        if ($schedule->update()) {
            return $view->data([
                'schedule_id' => $schedule->schedule_id
            ]);
        } else {
            return $this->recordError($schedule);
        } 
    }


    private function renderForm($schedule): ?View
    {
        $path = null;

        if ($schedule->schedule_id) {  
            $path = '/schedules/' . $schedule->schedule_id . '/update';
        } else {
            $path = '/schedules/create';
        }

        return View::init('tmpl/schedules/form.html', [
            'schedule_id' => $schedule->schedule_id,
            'schedule_date' => $this->msg->date($schedule->schedule_date),
            'schedule_name' => $schedule->schedule_name,
            'lesson_options' => $this->getLessonOptions($schedule),
            'lesson_time_options' => $this->getLessonTimeOptions($schedule),
            'path' => $path
        ]);
    }

    private function getLessonOptions($schedule): ?array
    {
        $options = null;

        $db = DataStore::init();
        $data = $db->data('
            select 
                lesson_id,
                lesson_name
            from lesson 
            where user_id = ? and organization_id is null
        ', [
            $this->current_user->id
        ]);

        if (!$data) {
            return $options;
        }

        foreach ($data as $r) {
            $options[] = [
                'name' => $r['lesson_name'],
                'value' => $r['lesson_id'],
                'selected' => $r['lesson_id'] == $schedule->lesson_id
            ];
        }

        return $options;
    }

    private function getLessonTimeOptions($schedule): ?array
    {
        $options = null;

        $db = DataStore::init();
        $data = $db->data('
            select 
                lesson_time_id,
                lesson_time_start_at,
                lesson_time_end_at
            from lesson_time
        ');

        if (!$data) {
            return $options;
        }

        foreach ($data as $r) {
            $options[] = [
                'name' => $r['lesson_time_start_at'] . ' - ' . $r['lesson_time_end_at'],
                'value' => $r['lesson_time_id'],
                'selected' => $r['lesson_time_id'] == $schedule->lesson_time_id
            ];
        }

        return $options;
    }

    private function renderSchedule(): ?View
    {
        $db = DataStore::init();

        if ($this->request->get('day')) {
            $datetime_start = new \DateTime(
                $this->request->get('year') . '-' . 
                $this->request->get('month') . '-' . 
                $this->request->get('day')
            );
        } else {
            $datetime_start = new \DateTime();

            $datetime_start->setTimestamp(strtotime("this week"));
        }

        $datetime_end = clone $datetime_start;
        $datetime_new_week = clone $datetime_start;
        $datetime_previous_week = clone $datetime_start;
        $lesson_time_data = $db->data('
            select *
            from lesson_time
        ');
        $schedule = null;

        foreach (range(1, 7) as $i) {
            $row = [
                'date' => $datetime_end->format($this->msg->t('datetime.format.date')),
                'date_raw' => $datetime_end->format('Y-m-d'),
                'lessons' => null
            ];

            foreach ($lesson_time_data as $value) {
                $row['lessons'][$value['lesson_time_id']] = [
                    'lesson_time_number' => $value['lesson_time_number'],
                    'lesson_time_start_at' => $value['lesson_time_start_at'],
                    'lesson_time_end_at' => $value['lesson_time_end_at'],
                    'lesson_name' => null,
                    'user_fullname' => null,
                    'schedule_name' => null,
                    'assignment_type' => null    
                ];
            }

            $schedule[$datetime_end->format('Y-m-d')] = $row;

            if ($i != 7) {
                $datetime_end->modify('+1 day');
            } 

            $datetime_new_week->modify('+1 day');
            $datetime_previous_week->modify('-1 day');
        }

        if ($this->current_user->organization_id) {
            $schedule_data = $db->data('
                select
                    s.*, 
                    lt.*, 
                    l.lesson_name, 
                    l.user_id, 
                    g.group_name,
                    null as organization_name
                from schedule s
                join lesson l on l.lesson_id = s.lesson_id
                join lesson_time lt on lt.lesson_time_id = s.lesson_time_id
                join organization o on o.organization_id = l.organization_id
                left join group_lesson gl on gl.lesson_id = l.lesson_id
                left join `group` g on g.group_id = gl.group_id
                where l.user_id = ?
                    and s.schedule_active = 1
                    and s.schedule_date between ? and ?
                order by s.lesson_time_id
            ', [
                $this->current_user->id,
                $firstday = $datetime_start->format('Y-m-d'),
                $lastday = $datetime_end->format('Y-m-d')
            ]);
        } else {
            $schedule_data = $db->data('
                select
                    s.*,
                    lt.*,
                    l.lesson_name,
                    l.user_id,
                    u.user_firstname,
                    u.user_lastname,
                    o.organization_name
                from schedule as s
                join lesson as l on l.lesson_id = s.lesson_id
                left join organization o on o.organization_id = l.organization_id
                left join group_lesson gl on gl.lesson_id = l.lesson_id
                left join group_user gu on gu.group_id = gl.group_id
                left join lesson_user as lu on lu.lesson_id = l.lesson_id
                join lesson_time as lt on lt.lesson_time_id = s.lesson_time_id
                left join user as u on u.user_id = l.user_id
                where (u.user_id = ? or ifnull(gu.user_id,lu.user_id) = ?)
                    and s.schedule_date between ? and ?
                    and s.schedule_active = 1
                order by s.lesson_time_id
            ', [
                $this->current_user->id,
                $this->current_user->id,
                $firstday = $datetime_start->format('Y-m-d'),
                $lastday = $datetime_end->format('Y-m-d')
            ]);
        }

        $schedules_assignments = null;
        $schedules_visits = null;
        $schedules_presences = null;

        if ($schedule_data) {
            foreach ($schedule_data as $value) {
                $schedules_assignments[$value['schedule_id']] = null;

                if ($value['user_id'] == $this->current_user->id) {
                    $schedules_visits[$value['schedule_id']] = null;
                } else {
                    $schedules_presences[$value['schedule_id']] = null;
                }
            }

            $schedules_visits = $this->populateSchedulesVisits($schedules_visits);
            $schedules_presences = $this->populateSchedulesPresences($schedules_presences);
            $schedules_assignments = $this->populateSchedulesAssignments($schedules_assignments);
        } 

        if ($schedule_data) {
            foreach ($schedule_data as $value) {
                if ($value['user_id'] == $this->current_user->id) {
                    $value['is_owner'] = true;
                    $value['schedule_edit_path'] = '/schedules/' . intval($value['schedule_id']) . '/edit';
                    $value['assignment_new_path'] = '/schedules/' . intval($value['schedule_id']) . 
                        '/assignments/new';
                    $value['schedule_visits_path'] = '/schedules/' . intval($value['schedule_id']) . 
                        '/visits/new';
                } else {
                    $value['is_owner'] = false;
                    $value['schedule_edit_path'] = null;

                }

                if ($this->current_user->organization_id) {
                    $value['lesson_participant'] = $value['group_name'] ?? null;
                } else {
                    if ($value['user_firstname']) {
                        $value['lesson_participant'] = $value['user_firstname'] . '  ' . $value['user_lastname'];
                    }

                    if ($value['user_id'] == $this->current_user->id) {
                        $value['organization_name'] = 'Jūsu priekšmets';
                        $value['lesson_participant'] = null;
                    } else {
                        $value['organization_name'] = $value['organization_name'] ?: 'Privats';
                    }
                }

                $value['assignments'] = $schedules_assignments[$value['schedule_id']] ?? null;
                $value['visits_count'] = $schedules_visits[$value['schedule_id']] ?? null;
                $value['presence'] = $schedules_presences[$value['schedule_id']] ?? null;

                $schedule[$value['schedule_date']]['lessons'][$value['lesson_time_id']] = $value;

            }
        }

        $actions = null;

        $actions[] = [
            'title' => 'Izveidot grafiku',
            'path' => '/schedules/new',
            'class_name' => 'js_modal'
        ];

        return View::init('tmpl/schedules/index.html')
            ->data([
                'schedule' => $schedule,
                'datetime_start' => $datetime_start->format('d.m.Y.'),
                'datetime_end' => $datetime_end->format('d.m.Y.'),
                'year' => $datetime_new_week->format('Y'),
                'month' => $datetime_new_week->format('m'),
                'day' => $datetime_new_week->format('d'),
                'previous_year' => $datetime_previous_week->format('Y'),
                'previous_month' => $datetime_previous_week->format('m'),
                'previous_day' => $datetime_previous_week->format('d'),
                'actions' => $actions
            ]);
    }

    private function populateSchedulesAssignments($assignments): ?array
    {
        if (!$assignments) {
            return $assignments;
        }
        
        $query = new DataQuery();

        $query
            ->select(
                'a.*',
                'g.grade_id',
                'g.grade_type',
                'g.grade_numeric',
                'g.grade_percent',
                'g.grade_included',
                'ifnull(g.grade_numeric,ifnull(g.grade_percent,g.grade_included)) as user_grade'
            )
            ->from('assignment a')
            ->leftJoin(
                'grade g on g.assignment_id = a.assignment_id' .
                ' and g.user_id = ?',
                $this->current_user->id
            )
            ->where(
                'a.schedule_id in ('.$query->placeholders($assignments).')',
                array_keys($assignments)
            );

        if (!$data = $query->fetchAll()) {
            return $assignments;    
        }

        foreach ($data as $value) {
            $assignments[$value['schedule_id']][] = [
                'assignment_id' => $value['assignment_id'],
                'assignment_type' => $this->msg->t('assignment.types.'.$value['assignment_type']),
                'user_grade' => $value['user_grade']
            ];
        }

        return $assignments;
    }

    private function populateSchedulesVisits($visits): ?array
    {
        if (!$visits) {
            return $visits;
        }
        
        $query = new DataQuery();

        $query
            ->select(
                'v.schedule_id',
                'sum(v.visit_presence) as visit_presence',
                'count(gu.group_user_id) as group_users_count',
                'count(lu.lesson_user_id) as lesson_users_count'
            )
            ->from('visit as v')
            ->join('schedule as s on s.schedule_id = v.schedule_id')
            ->leftJoin('group_user as gu on gu.group_id = s.group_id')
            ->leftJoin('lesson_user as lu on lu.lesson_id = s.lesson_id')
            ->where(
                'v.schedule_id in ('.$query->placeholders($visits).')',
                array_keys($visits)
            )
            ->group('v.schedule_id');

        if (!$data = $query->fetchAll()) {
            return $visits;    
        }

        foreach ($data as $value) {
            $visits[$value['schedule_id']] = [[
                'presence_count' => $value['visit_presence'],
                'total_count' => intval($value['group_users_count'] ?: $value['lesson_users_count'])
            ]];
        }

        return $visits;
    }

    private function populateSchedulesPresences($presences): ?array
    {
        if (!$presences) {
            return $presences;
        }
        
        $query = new DataQuery();

        $query
            ->select(
                'v.schedule_id',
                'v.visit_presence',
                '(' .
                ' select 1 as one' .
                ' from excused_absence ea' .
                ' where ea.group_user_id = gu.group_user_id' .
                '  and ea.excused_absence_from <= s.schedule_date' .
                '  and ea.excused_absence_to >= s.schedule_date' .
                ') as excused_absence'
            )
            ->from('visit as v')
            ->join('schedule s on s.schedule_id = v.schedule_id')
            ->leftJoin('group_user gu on gu.group_id = s.group_id and gu.user_id = v.user_id')
            ->where(
                'v.schedule_id in ('.$query->placeholders($presences).')',
                array_keys($presences)
            )
            ->where('v.user_id = ?', $this->current_user->id);

        if (!$data = $query->fetchAll()) {
            return $presences;    
        }

        foreach ($data as $value) {
            $presences[$value['schedule_id']] = [[
                'visit_presence' => $value['visit_presence'],
                'excused_absence' => !!$value['excused_absence'],
            ]];
        }

        return $presences;
    }

    private function getAssignments($schedule): ?array
    {
        $db = DataStore::init();

        $data = $db->data('
            select 
                assignment_type
            from assignment
            where schedule_id = ?
        ', [
            $schedule->schedule_id
        ]);

        return $data;
    }

}