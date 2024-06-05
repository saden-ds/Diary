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
        'lesson_id' => ['type' => 'integer'],
        'lesson_time_id' => ['type' => 'integer']
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
            ->select('schedule_id')
            ->from('schedule')
            ->where('lesson_id = ?', $this->lesson_id)
            ->where('schedule_date = ?', $this->formatDate($this->schedule_date))
            ->where('lesson_time_id = ?', $this->lesson_time_id);

        if ($this->schedule_id) {
            $query->where('schedule_id != ?', $this->schedule_id);
        }

        if ($query->first()) {
            $this->addError('base', $this->msg->t('schedule.message.error.exists'));
        }
    }

}