<?php

namespace App\Controllers;

use App\Base\Exceptions\ForbiddenException;
use App\Base\Exceptions\NotFoundException;
use App\Base\View;
use App\Base\DataStore;
use App\Models\Lesson;
use App\Models\LessonInvite;
use App\Models\LessonUser;

class LessonInvitesController extends PrivateController
{

    public function newAction(): ?View
    {
        $lesson = $this->getLesson();

        return View::init('tmpl/lesson_invites/form.html', [
            'lesson_id' => $lesson->lesson_id,
            'lesson_name' => $lesson->lesson_name,
            'email' => null,
            'path' => '/lessons/invites/create'
        ]);  
    }

    public function createAction(): ?View
    {
        $lesson = $this->getLesson();
        $invite = new LessonInvite([
            'lesson_id' => $lesson->lesson_id,
            'lesson_invite_email' => $this->request->get('lesson_invite_email')
        ]);
        $view = new View();

        if ($invite->lesson_invite_email === $this->current_user->email) {
            $invite->addError("base", "Jūs nevarat uzaicināt sevi");
        }

        if ($invite->create()) {
            $this->flash->notice('Lietotājs veiksmīgi uzaicināts!');
            return $view->data([
                'lesson_invite_id' => $invite->lesson_invite_id
            ]);
        } else {
            return $this->recordError($invite);
        } 
    }

    public function deleteAction(): ?View
    {
        $invite = LessonInvite::find($this->request->get('id'));

        if (!$invite) {
            throw new NotFoundException();
        }

        $lesson = Lesson::find($invite->lesson_id);

        if (!$lesson || $lesson->user_id != $this->current_user->id) {
            throw new ForbiddenException();
        }

        $invite->delete();

        $this->flash->notice('Lietotājs veiksmīgi dzēsts!');

        return $this->redirect('/lessons/' . $invite->lesson_id);
    }

    public function acceptAction(): ?View
    {
        $invite = LessonInvite::find($this->request->get('id'));

        if (!$invite) {
            throw new NotFoundException();
        }

        if ($invite->lesson_invite_email != $this->current_user->email) {
            throw new ForbiddenException();
        }

        $lesson_user = new LessonUser([
            'lesson_id' => $invite->lesson_id,
            'user_id' => $this->current_user->id
        ]);

        if (!$lesson_user->create()) {
            return $this->recordError($lesson_user);
        }

        $invite->delete();

        $this->flash->notice('Uzaicinājums veiksmīgi apstiprināts!');

        return $this->redirect('/lessons/' . $lesson_user->lesson_id);
    }

    public function declineAction(): ?View
    {
        $invite = LessonInvite::find($this->request->get('id'));

        if (!$invite) {
            throw new NotFoundException();
        }

        if ($invite->lesson_invite_email != $this->current_user->email) {
            throw new ForbiddenException();
        }

        $invite->delete();

        $this->flash->notice('Uzaicinājums noraidīts!');

        return $this->redirect('/');
    }


    private function getLesson(): Lesson
    {
        $lesson = Lesson::find($this->request->get('lesson_id'));

        if (!$lesson) {
            throw new NotFoundException();
        }

        if (
            $lesson->organization_id ||
            $lesson->user_id != $this->current_user->id
        ) {
            throw new ForbiddenException();
        }

        return $lesson;
    }

} 