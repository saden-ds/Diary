<?php

namespace App\Controllers;

use App\Base\Exceptions\ForbiddenException;
use App\Base\View;
use App\Models\LessonUser;
use App\Models\Lesson;

class LessonUsersController extends PrivateController
{
    public function deleteAction(): ?View
    {
        $lesson_user = LessonUser::find($this->request->get('id'));

        if (!$lesson_user) {
            throw new NotFoundException();
        }

        $lesson = Lesson::find($lesson_user->lesson_id);

        if (!$lesson || $lesson->user_id != $this->current_user->id) {
            throw new ForbiddenException();
        }

        $lesson_user->delete();

        return $this->redirect('/lessons/' . $lesson_user->lesson_id);
    }
}