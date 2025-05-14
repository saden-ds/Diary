<?php

namespace App\Controllers;

use App\Base\Exceptions\NotFoundException;
use App\Base\Exceptions\ForbiddenException;
use App\Base\DataQuery;
use App\Base\FileParams;
use App\Base\View;
use App\Models\Assignment;
use App\Models\Assignment\File;
use App\Services\Assignment\FilesCollection;

class AssignmentFilesController extends PrivateController
{
    public function indexAction(): ?View
    {   
        $assignment_file_id = $this->request->get('assignment_file_id');

        if (empty($assignment_file_id)) {
            throw new NotFoundException();
        }

        $assignment = Assignment::findAssignmentByIdAndUserId(
            $this->request->get('assignment_id'),
            $this->current_user->id
        );
        
        if (empty($assignment)) {
            throw new ForbiddenException();
        }

        echo FilesCollection::renderAssignmentFiles($assignment, [
            'assignment_file_id' => $assignment_file_id,
            'current_user_id' => $this->current_user->id
        ]);

        return null;
            
    }

    public function showAction(): ?View
    {
        $assignment_file = File::find($this->request->get('id'));

        if (!$assignment_file || !$assignment_file->file) {
            throw new NotFoundException();
        }

        $assignment = Assignment::findAssignmentByIdAndUserId(
            $assignment_file->assignment_id,
            $this->current_user->id
        );

        if (!$assignment) {
            throw new ForbiddenException();
        }

        $file = $assignment_file->file;

        if ($assignment_file->assignment_file_type) {
            header('Content-Type: ' . $assignment_file->assignment_file_type);
        } else {
            header('Content-Type: application/octet-stream');
        }

        header('Content-Disposition: attachment; filename="' . $assignment_file->assignment_file_name . '"');
        header('Content-Length: ' . $file->size());

        echo $file->getContents();

        return null;
    }

    public function createAction(): View
    {
        $assignment = Assignment::findAssignmentByIdAndUserId(
            $this->request->get('assignment_id'),
            $this->current_user->id
        );
        
        if (empty($assignment)) {
            throw new NotFoundException();
        }

        $tmp_file = FileParams::set($_FILES['file'])->get();

        $assignment_file = new File([
            'tmp_file' => $tmp_file,
            'assignment_id' => $assignment->assignment_id,
            'user_id' => $this->current_user->id
        ]);

        if (!$assignment_file->create()) {
            return $this->recordError($assignment_file);
        }

        return View::init([
            'assignment_file_id' => $assignment_file->assignment_file_id,
            'assignment_id' => $assignment_file->assignment_id
        ]);
    }

    public function deleteAction(): View
    {
        $assignment_file = File::findByIdAndUserId(
            $this->request->get('id'),
            $this->current_user->id
        );

        if (empty($assignment_file)) {
            throw new NotFoundException();
        }

        if (!$assignment_file->delete()) {
            return $this->recordError($assignment_file);
        }

        return View::init([
            'assignment_file_id' => $assignment_file->assignment_file_id,
            'assignment_id' => $assignment_file->assignment_id
        ]);
    }
}
