<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\DataStore;
use App\Base\DataQuery;
use App\Base\View;
use App\Models\Grade;
use DateTime;

class MainController extends ApplicationController
{
    public function indexAction(): ?View
    {
        if (!$this->current_user->isSignedIn()) {
            return View::init('tmpl/main/index.tmpl')
                ->layout('blank')
                ->data([
                    'app_name' => $this->config->get('title')
                ])
                ->meta('description', $this->msg->t('meta.description.main'));   
        }

        if (!$this->current_user->confirmed) {
            return View::init('tmpl/confirmations/index.tmpl')
                ->layout('blank')
                ->data([
                    'text' => $this->msg->t('user_confirmation.description', [
                        'email' => $this->current_user->email
                    ]),
                    'app_name' => $this->config->get('title')
                ]);
        }
        
        return $this->renderSchedule();
    }


    private function renderSchedule(): ?View
    {
        $db = DataStore::init();

        if ($this->request->get('day')) {
            $datetime_start = new DateTime(
                $this->request->get('year') . '-' . 
                $this->request->get('month') . '-' . 
                $this->request->get('day')
            );
        } else {
            $datetime_start = new DateTime();

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
                    o.organization_name,
                    g.group_name
                from schedule as s
                join lesson as l on l.lesson_id = s.lesson_id
                left join organization o on o.organization_id = l.organization_id
                left join group_lesson gl on gl.lesson_id = l.lesson_id
                left join group_user gu on gu.group_id = gl.group_id
                    and gu.group_id = s.group_id
                left join `group` g on g.group_id = gl.group_id
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
                $actions = null;

                if ($value['user_id'] == $this->current_user->id) {
                    $value['is_owner'] = true;

                    $actions[] = [
                        'title' => 'Rediģēt grafiku',
                        'path' => '/schedules/' . intval($value['schedule_id']) . '/edit',
                        'class_name' => 'js_modal'
                    ];
                    $actions[] = [
                        'title' => 'Pievienot uzdevumu',
                        'path' =>  '/schedules/' . intval($value['schedule_id']) . '/assignments/new',
                        'class_name' => 'js_modal'
                    ];
                    $actions[] = [
                        'title' => 'Atzīmēt apmeklējumu',
                        'path' => '/schedules/' . intval($value['schedule_id']) . 
                        '/visits/new',
                        'class_name' => 'js_modal'
                    ];
                } else {
                    $value['is_owner'] = false;
                }


                if ($value['user_id'] == $this->current_user->id) {
                    $value['lesson_teacher'] = null;
                } else {
                    $value['lesson_teacher'] = $value['user_firstname'] . ' ' . $value['user_firstname'];
                }

                $value['assignments'] = $schedules_assignments[$value['schedule_id']] ?? null;
                $value['visits_count'] = $schedules_visits[$value['schedule_id']] ?? null;
                $value['presence'] = $schedules_presences[$value['schedule_id']] ?? null;
                $value['actions'] = $actions;

                $schedule[$value['schedule_date']]['lessons'][$value['lesson_time_id']] = $value;

            }
        }

        $actions = null;

        if (!$this->current_user->organization_id) {
            $actions[] = [
                'title' => 'Izveidot grafiku',
                'path' => '/schedules/new',
                'class_name' => 'js_modal'
            ];
        }

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
                'g.grade_included'
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
            $grade = new Grade($value, true);

            $assignments[$value['schedule_id']][] = [
                'assignment_id' => $value['assignment_id'],
                'assignment_type' => $this->msg->t('assignment.types.'.$value['assignment_type']),
                'grade' => $grade->grade_formatted
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
                'count(ifnull(gu.group_user_id,lu.lesson_user_id)) as total_count'
            )
            ->from('visit as v')
            ->join('schedule as s on s.schedule_id = v.schedule_id')
            ->leftJoin('group_user as gu on gu.group_id = s.group_id')
            ->leftJoin('lesson_user as lu on lu.lesson_id = s.lesson_id')
            ->where(
                'v.schedule_id in ('.$query->placeholders($visits).')',
                array_keys($visits)
            )
            ->where('ifnull(gu.user_id,lu.user_id) = v.user_id')
            ->group('v.schedule_id');

        if (!$data = $query->fetchAll()) {
            return $visits;    
        }

        foreach ($data as $value) {
            $visits[$value['schedule_id']] = [[
                'presence_count' => $value['visit_presence'],
                'total_count' => $value['total_count']
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