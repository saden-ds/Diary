<?php

namespace App\Models;

use App\Validators\Presence as ValidatorPresence;

class Visit extends Model
{
    static $attributes_mapping = [
        'visit_id' => ['type' => 'integer'],
        'visit_presence' => ['type' => 'boolean', 'default' => true],
        'user_id' => ['type' => 'integer'],
        'schedule_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'visit';
    protected static ?string $primary_key = 'visit_id';


    public function save($attributes = null): bool
    {
        if ($this->visit_id) {
            return $this->update($attributes);
        }

        return $this->create($attributes);
    }

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
            'schedule_id'
        ]);

        $presence->validate($this);
    }

}