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
        'schedule_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'assignment';
    protected static ?string $primary_key = 'assignment_id';



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
            delete from assignment
            where assignment_id = ? 
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