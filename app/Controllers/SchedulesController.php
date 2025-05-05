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
}