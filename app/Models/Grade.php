<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class Grade extends Model
{
    const TYPES = ['numeric','percent','included'];

    static $attributes_mapping = [
        'grade_id' => ['type' => 'integer'],
        'grade_type' => ['type' => 'string'],
        'grade_numeric' => ['type' => 'integer'],
        'grade_percent' => ['type' => 'integer'],
        'grade_included' => ['type' => 'integer'],
        'assignment_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'grade';
    protected static ?string $primary_key = 'grade_id';


    public function getFormattedGrade(): ?string
    {
        if ($this->grade_type === 'percent') {
            return $this->grade_percent . '%';
        } elseif ($this->grade_type === 'included') {
            return $this->grade_included ? 'i' : 'ni';
        } elseif ($this->grade_numeric !== null && !$this->grade_numeric) {
            return 'n/v';
        } else {
            return $this->grade_numeric;
        }
    }

    public function setGrade($type, $value)
    {
        $this->grade_type = $type;
        $this->grade_numeric = null;
        $this->grade_percent = null;
        $this->grade_included = null;

        if ($this->grade_type === 'numeric') {
            $this->grade_numeric = $value;
        } elseif ($this->grade_type === 'percent') {
            $this->grade_percent = $value;
        } elseif ($this->grade_type === 'included') {
            $this->grade_included = $value;
        }
    }

    public function getGrade()
    {
        if ($this->grade_type === 'numeric') {
            return $this->grade_numeric;
        } elseif ($this->grade_type === 'percent') {
            return $this->grade_percent;
        } elseif ($this->grade_type === 'included') {
            return $this->grade_included;
        }

        return null;
    }

    public function save($attributes = null): bool
    {
        if ($this->grade_id) {
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
        if (!$this->grade_id) {
            return false;
        }

        return !!$this->db->query('
            delete from grade
            where grade_id = ?
        ', $this->grade_id);
    }


    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'grade_type', 'assignment_id'
        ]);

        if ($this->getGrade() === null || $this->getGrade() === '') {
            $this->addError('grade', $this->msg->t('error.empty'));
        }

        $presence->validate($this);
    }

}