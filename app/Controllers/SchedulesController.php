<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
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
            ->layout('tmpl/blank.tmpl')
            ->data([
                // 'user_name' => $user['name']
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
            where user_id = ?
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

        
        $schedule_data = $db->data('
            select
                s.*,
                lt.*,
                l.lesson_name,
                l.user_id,
                u.user_firstname,
                u.user_lastname
            from schedule as s
            join lesson as l on l.lesson_id = s.lesson_id
            left join lesson_user as lu on lu.lesson_id = l.lesson_id
            join lesson_time as lt on lt.lesson_time_id = s.lesson_time_id
            left join user as u on u.user_id = l.user_id
            where (lu.user_id = ? or l.user_id = ?)
                and s.schedule_date between ? and ?
            order by s.lesson_time_id
        ', [
            $this->current_user->id,
            $this->current_user->id,
            $firstday = $datetime_start->format('Y-m-d'),
            $lastday = $datetime_end->format('Y-m-d')
        ]);

        $schedules_assignments = null;

        if ($schedule_data) {
            foreach ($schedule_data as $value) {
                $schedules_assignments[$value['schedule_id']] = null;
            }

            $schedules_assignments = $this->populateSchedulesAssignments($schedules_assignments);
        } 

        if ($schedule_data) {
            foreach ($schedule_data as $value) {
                if ($value['user_id'] == $this->current_user->id) {
                    $value['is_owner'] = true;
                    $value['schedule_edit_path'] = '/schedules/' . intval($value['schedule_id']) . '/edit';
                    $value['assignment_new_path'] = '/schedules/' . intval($value['schedule_id']) . 
                        '/assignments/new';
                } else {
                    $value['is_owner'] = false;
                    $value['schedule_edit_path'] = null;

                }

                if ($value['user_firstname']) {
                    $value['user_fullname'] = $value['user_firstname'] . '  ' . $value['user_lastname'];
                }

                $value['assignments'] = $schedules_assignments[$value['schedule_id']] ?? null;

                $schedule[$value['schedule_date']]['lessons'][$value['lesson_time_id']] = $value;

            }
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
                'previous_day' => $datetime_previous_week->format('d')
            ]);
    }

    private function populateSchedulesAssignments($assignments): ?array
    {
        if (!$assignments) {
            return $assignments;
        }
        
        $db = DataStore::init();

        $data = $db->data('
            select *
            from assignment
            where schedule_id in ('.$db->placeholders($assignments).')
        ', array_keys($assignments));

        if (!$data) {
            return $assignments;    
        }

        foreach ($data as $value) {
            $assignments[$value['schedule_id']][] = [
                'assignment_id' => $value['assignment_id'],
                'assignment_type' => $this->msg->t('assignment.types.'.$value['assignment_type'])
            ];
        }

        return $assignments;
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