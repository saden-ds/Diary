<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class GroupLesson extends Model
{
    static $attributes_mapping = [
        'group_lesson_id' => ['type' => 'integer'],
        'group_id' => ['type' => 'integer'],
        'lesson_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'group_lesson';
    protected static ?string $primary_key = 'group_lesson_id';


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
            delete from group_lesson 
            where group_lesson_id = ? 
        ', $this->group_lesson_id);
    }


    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'group_id', 'lesson_id'
        ]);

        $presence->validate($this);
    }

}