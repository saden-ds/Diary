<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class LessonInvite extends Model
{
    static $attributes_mapping = [
        'lesson_invite_id' => ['type' => 'integer'],
        'lesson_invite_email' => ['type' => 'string'],
        'lesson_id' => ['type' => 'integer'],
    ];

    protected static ?string $table_name = 'lesson_invite';
    protected static ?string $primary_key = 'lesson_invite_id';


    public function create($attributes = null): bool
    {
        return $this->validateAndCreateRecord($attributes);
    }

    public function delete(): bool
    {
        $db = DataStore::init();

        return !!$db->query('
            delete from lesson_invite 
            where lesson_invite_id = ? 
        ', $this->lesson_invite_id);
    }

    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'lesson_id', 'lesson_invite_email'
        ]);

        $presence->validate($this);

        if (!$this->hasErrors()) {
            $this->validateExists();
        }
    }

    private function validateExists(): void
    {
        if ($this->db->row("
            select
                lu.lesson_user_id
            from lesson_user as lu 
            join user as u on u.user_id = lu.user_id
            where lu.lesson_id = ? and u.user_email = ?
        ", [
            $this->lesson_id,
            $this->lesson_invite_email
        ])) {
            $this->addError("base", "Šis lietotājs jau ir uzaicināts");
        }
    }


}