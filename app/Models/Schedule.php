<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class Schedule extends Model
{
    static $attributes_mapping = [
        'schedule_id' => ['type' => 'integer'],
        'schedule_date' => ['type' => 'date'],
        'schedule_name' => ['type' => 'string'],
        'group_id' => ['type' => 'integer'],
        'lesson_id' => ['type' => 'integer'],
        'lesson_time_id' => ['type' => 'integer'],
        'schedule_active' => ['type' => 'boolean', 'default' => false]
    ];

    protected static ?string $table_name = 'schedule';
    protected static ?string $primary_key = 'schedule_id';



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
        return !!$this->db->query('
            delete s
            from schedule s
            left join assignment a on a.schedule_id = s.schedule_id
            left join grade g on g.assignment_id = a.assignment_id
            where s.schedule_id = ?
        ', $this->schedule_id);
    }


    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'schedule_date', 'lesson_id', 'lesson_time_id'
        ]);

        $presence->validate($this);

        if ($this->schedule_date && date_create($this->schedule_date) === false) {
            $this->addError('schedule_date', $this->msg->t('error.invalid_date_format'));
        }

        if (!$this->hasErrors()) {
            $this->validateUniquiness();
        }
    }


    private function validateUniquiness(): void
    {
        $query = new DataQuery();

        $query
            ->select('u.user_firstname', 'u.user_lastname')
            ->from('lesson x')
            ->join('lesson l on l.user_id = x.user_id')
            ->join('user u on u.user_id = l.user_id')
            ->join('schedule s on s.lesson_id = l.lesson_id')
            ->where('x.lesson_id = ?', $this->lesson_id)
            ->where('s.schedule_date = ?', $this->formatDate($this->schedule_date))
            ->where('s.lesson_time_id = ?', $this->lesson_time_id);

        if ($this->schedule_id) {
            $query->where('s.schedule_id != ?', $this->schedule_id);
        }

        if ($data = $query->first()) {
            $this->addError('base', $this->msg->t('schedule.message.error.user_busy', [
                'name' => $data['user_firstname'] . ' ' . $data['user_lastname']
            ]));
        }
    }

}