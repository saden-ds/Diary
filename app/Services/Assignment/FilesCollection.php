<?php

namespace App\Services\Assignment;

use App\Base\DataQuery;
use App\Base\Message;
use App\Base\Tmpl;
use App\Models\Assignment;

class FilesCollection
{
    protected Message $msg;

    public static function renderAssignmentFiles(Assignment $assignment, ?array $params = []): ?string
    {
        $instance = new self();
        $tmpl = Tmpl::init();
        $data = $instance->getData($assignment, $params);

        if ($data) {
            return $tmpl->file('/tmpl/assignments/files/_index_row.tmpl', [
                'items' => $data
            ]);
        }

        return null;
    }


    public function __construct()
    {
        $this->msg = Message::init();
    }

    public function getData(Assignment $assignment, ?array $params = []): ?array
    {
        $query = new DataQuery();
        $params = array_merge([
            'assignment_file_id' => null,
            'user_id' => null,
            'except_user_id' => null,
            'current_user_id' => null,
            'readonly' => false 
        ], $params);

        $query
          ->select('af.*', 'u.user_firstname', 'u.user_lastname')
          ->from('assignment_file as af')
          ->join('user u on u.user_id = af.user_id')
          ->where('af.assignment_id = ?', $assignment->assignment_id);

        if ($params['assignment_file_id']) {
            $query->where('af.assignment_file_id = ?', $params['assignment_file_id']);
        }

        if ($params['user_id']) {
            $query->where('af.user_id = ?', $params['user_id']);
        }

        if ($params['except_user_id']) {
            $query->where('af.user_id != ?', $params['except_user_id']);
        }

        $data = $query->fetchAll();

        if ($data) {
            foreach ($data as $k => $v) {
                $v['assignment_file_path'] = '/assignments/files/' 
                    . intval($v['assignment_file_id']);

                if (!$params['readonly'] && $params['current_user_id'] == $v['user_id']) {
                    $v['assignment_file_delete_path'] = '/assignments/files/' 
                        . intval($v['assignment_file_id']) . '/delete';
                } else {
                    $v['assignment_file_delete_path'] = null;
                }

                $v['assignment_file_created_at'] = $this->msg->l($v['assignment_file_created_at']);

                if ($params['except_user_id']) {
                    $v['user_fullname'] = $v['user_firstname'] . ' ' . $v['user_lastname'];
                } else {
                    $v['user_fullname'] = null;
                }

                $data[$k] = $v;
            }
        }

        return $data;
    }
}