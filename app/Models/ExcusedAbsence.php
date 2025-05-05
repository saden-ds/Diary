<?php

namespace App\Models;

use App\Validators\Presence as ValidatorPresence;

class ExcusedAbsence extends Model
{    
    static $attributes_mapping = [
        'excused_absence_id' => ['type' => 'integer'],
        'excused_absence_from' => ['type' => 'date'],
        'excused_absence_to' => ['type' => 'date'],
        'group_user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'excused_absence';
    protected static ?string $primary_key = 'excused_absence_id';


    public function save($attributes = null): bool
    {
        if ($this->excused_absence_id) {
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

    public function delete(): bool
    {
        if (!$this->excused_absence_id) {
            return false;
        }

        return !!$this->db->query('
            delete from excused_absence
            where excused_absence_id = ?
        ', $this->excused_absence_id);
    }


    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'excused_absence_from', 'excused_absence_to', 'group_user_id'
        ]);

        $presence->validate($this);
    }

}