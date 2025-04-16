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

    public function delete(): bool
    {
        $db = DataStore::init();

        return !!$db->query('
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
    }

}