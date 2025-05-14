<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class Assignment extends Model
{

    const TYPES = [
        'test',
        'work'
    ];

    static $attributes_mapping = [
        'assignment_id' => ['type' => 'integer'],
        'assignment_type' => ['type' => 'string'],
        'assignment_description' => ['type' => 'string'],
        'assignment_end_datetime' => ['type' => 'datetime'],
        'assignment_created_at' => ['type' => 'datetime'],
        'schedule_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'assignment';
    protected static ?string $primary_key = 'assignment_id';


    public static function findAssignmentByIdAndUserId(int $id, int $user_id): ?Assignment
    {
        $query = new DataQuery();

        $query
            ->select('a.*')
            ->from('assignment as a')
            ->join('schedule as s on s.schedule_id = a.schedule_id')
            ->join('lesson as l on l.lesson_id = s.lesson_id')
            ->leftJoin('group_user as gu on gu.group_id = s.group_id')
            ->leftJoin('lesson_user as lu on lu.lesson_id = s.lesson_id')
            ->join('user as u on u.user_id = ifnull(gu.user_id,lu.user_id)')
            ->where('a.assignment_id = ?', $id)
            ->where('(l.user_id = ? or u.user_id = ?)', [
                $user_id,
                $user_id
            ]);

        if (!$id || !$user_id || !$r = $query->fetch()) {
            return null;
        }

        return new Assignment($r, true);
    }

    public function setAssignmentEndDatetime($value): void
    {
        if (is_array($value)) {
            if (
                isset($value['date']) &&
                isset($value['hour']) &&
                isset($value['minute']) &&
                $value['date']
            ) {
                $value = $value['date'].' '.$value['hour'].':'.$value['minute'].':00';

                if (date_create($value) === false) {
                    $this->addError('assignment_end_datetime', $this->msg->t('error.invalid_date_format'));
                }
            } else {
                $value = null;
            }
        }

        $this->assignAttribute('assignment_end_datetime', $value);
    }

    public function create($attributes = null): bool
    {
        return $this->validateAndCreateRecord($attributes);
    }

    public function update($attributes = null): bool
    {
        return $this->validateAndUpdateRecord($attributes);
    }

    public function delete(): bool
    {
        $db = DataStore::init();

        return !!$db->query('
            delete a, g
            from assignment a
            left join grade g on g.assignment_id = a.assignment_id
            where a.assignment_id = ? 
        ', $this->assignment_id);
    }

    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'assignment_type', 'assignment_description', 'assignment_end_datetime', 'schedule_id'
        ]);

        $presence->validate($this);

        if ($this->assignment_end_datetime && date_create($this->assignment_end_datetime) === false) {
            $this->addError('assignment_end_datetime', $this->msg->t('error.invalid_date_format'));
        }
    }

}