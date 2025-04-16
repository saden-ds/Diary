<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class Lesson extends Model
{
    static $attributes_mapping = [
        'lesson_id' => ['type' => 'integer'],
        'lesson_name' => ['type' => 'string'],
        'lesson_description' => ['type' => 'string'],
        'user_id' => ['type' => 'integer'],
        'organization_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'lesson';
    protected static ?string $primary_key = 'lesson_id';


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
            'lesson_name', 'lesson_description', 'user_id'
        ]);

        $presence->validate($this);
    }

}