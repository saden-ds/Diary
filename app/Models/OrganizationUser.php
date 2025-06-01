<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class OrganizationUser extends Model
{
    const ROLES = ['teacher', 'admin'];

    static $attributes_mapping = [
        'organization_user_id' => ['type' => 'integer'],
        'organization_user_role' => ['type' => 'string'],
        'organization_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'organization_user';
    protected static ?string $primary_key = 'organization_user_id';

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
        if ($this->db->row('
            select 1 as one
            from lesson
            where user_id = ?
        ', $this->user_id)) {
            $this->addError(
                'base',
                $this->msg->t('organization_user.message.error.delete_lesson_user')
            );

            return false;
        }

        if ($this->db->row('
            select 1 as one
            from `group`
            where organization_user_id = ?
        ', $this->user_id)) {
            $this->addError(
                'base',
                $this->msg->t('organization_user.message.error.delete_organization_user')
            );

            return false;
        }

        return !!$this->db->query('
            delete from organization_user 
            where organization_user_id = ? 
        ', $this->organization_user_id);
    }

    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'organization_id', 'user_id'
        ]);

        $presence->validate($this);

        $this->validateOrganizationUserRoleChange();
    }

    private function validateOrganizationUserRoleChange(): void
    {
        if (!$this->user_id || $this->organization_user_role === 'teacher') {
            return;
        }

        if ($this->db->row('
            select 1 as one
            from lesson
            where user_id = ?
                and organization_id = ?
        ', [$this->user_id, $this->organization_id])) {
            $this->addError(
                'base',
                $this->msg->t('organization_user.message.error.lesson_user_not_teacher')
            );
        }
    }

}