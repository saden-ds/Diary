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
        $lesson_user = LessonUser::find($this->request->get('lesson_user_id'));

        $lesson = Lesson::find($lesson_user->lesson_id);

        if ($lesson->user_id != $this->current_user->id) {
            // throw new ForbiddenException();
            return $this->redirect('/lessons/' . $lesson->lesson_id);
        }

        $lesson_user->delete();

        return $this->redirect('/lessons/' . $lesson->lesson_id);

        // $view = new View();

        // if ($lesson_user->delete()) {
        //     return $view->data([
        //         'lesson_user_id' => $lesson_user->lesson_user_id
        //     ]);
        // } else {
        //     return $this->recordError($lesson_user);
        // }
    }


    // lesson_user_delete_path
}