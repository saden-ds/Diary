<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class LessonUser extends Model
{
    static $attributes_mapping = [
        'lesson_user_id' => ['type' => 'integer'],
        'lesson_user_created_at' => ['type' => 'datetime'],
        'lesson_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'lesson_user';
    protected static ?string $primary_key = 'lesson_user_id';

    public function create($attributes = null): bool
    {
        return $this->validateAndCreateRecord($attributes);
    }

    public function delete(): bool
    {
        $db = DataStore::init();

        return !!$db->query('
            delete from lesson_user 
            where lesson_user_id = ? 
        ', $this->lesson_user_id);
    }


    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'lesson_id', 'user_id'
        ]);

        $presence->validate($this);
    }

}