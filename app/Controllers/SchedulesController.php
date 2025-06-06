<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Base\DataQuery;
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
            $this->flash->notice('Nodarbība veiksmīgi ieplānota!');
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

        return $this->renderForm($schedule, $lesson);
    }

    public function updateAction(): ?View
    {
        $schedule = Schedule::find($this->request->get('id'));
        $view = new View();

        $schedule->setAttributes($this->request->permit([
            'schedule_name', 'schedule_date', 'lesson_id', 'lesson_time_id'
        ]));

        if ($schedule->update()) {
            $this->flash->notice('Izmaiņas veiksmīgi saglabātas!');
            return $view->data([
                'schedule_id' => $schedule->schedule_id
            ]);
        } else {
            return $this->recordError($schedule);
        } 
    }


    private function renderForm(Schedule $schedule, ?Lesson $lesson = null): ?View
    {
        $path = null;

        if ($schedule->schedule_id) {  
            $title = 'Rediģēt grafiku';
            $path = '/schedules/' . $schedule->schedule_id . '/update';
        } else {
            $title = 'Jauns grafiks';
            $path = '/schedules/create';
        }

        if ($lesson && !$this->current_user->canAdmin($lesson->organization_id)) {
            $lesson_name = null;

            return View::init('tmpl/schedules/form_short.tmpl', [
                'schedule_id' => $schedule->schedule_id,
                'schedule_date' => $this->msg->date($schedule->schedule_date),
                'schedule_name' => $schedule->schedule_name,
                'lesson_name' => $lesson_name,
                'lesson_time_options' => $this->getLessonTimeOptions($schedule),
                'path' => $path
            ]);
        } else {
            return View::init('tmpl/schedules/form.tmpl', [
                'title' => $title,
                'schedule_id' => $schedule->schedule_id,
                'schedule_date' => $this->msg->date($schedule->schedule_date),
                'schedule_name' => $schedule->schedule_name,
                'lesson_options' => $this->getLessonOptions($schedule),
                'lesson_time_options' => $this->getLessonTimeOptions($schedule),
                'path' => $path
            ]);
        }
    }

    private function getLessonOptions($schedule): ?array
    {
        $options = null;

        $query = new DataQuery();

        $query
            ->select(
                'lesson_id',
                'lesson_name'
            )
            ->from('lesson')
            ->where('user_id = ?', $this->current_user->id);
        
        if ($this->current_user->organization_id) {
            $query->where('organization_id = ?', $this->current_user->organization_id);
        } else {
            $query->where('organization_id is null');
        }

        if (!$data = $query->fetchAll()) {
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

        $query = new DataQuery();

        $query
            ->select(
                'lesson_time_id',
                'lesson_time_start_at',
                'lesson_time_end_at'
            )
            ->from('lesson_time');

        if (!$data = $query->fetchAll()) {
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