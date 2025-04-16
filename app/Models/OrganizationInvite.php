<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class OrganizationInvite extends Model
{
    static $attributes_mapping = [
        'organization_invite_id' => ['type' => 'integer'],
        'organization_invite_email' => ['type' => 'string'],
        'organization_invite_role' => ['type' => 'string'],
        'organization_id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'organization_invite';
    protected static ?string $primary_key = 'organization_invite_id';


    public function create($attributes = null): bool
    {

        return $this->validateAndCreateRecord($attributes);
    }

    public function delete(): bool
    {
        $db = DataStore::init();

        return !!$db->query('
            delete from organization_invite 
            where organization_invite_id = ? 
        ', $this->organization_invite_id);
    }

    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'organization_invite_email', 'organization_invite_role', 'organization_id', 'user_id'
        ]);

        $presence->validate($this);

        if (!$this->hasErrors()) {
            $this->validateOrganizationUserExists();
        }

        if (!$this->hasErrors()) {
            $this->validateOrganizationInviteExists();
        }
    }

    private function validateOrganizationUserExists(): void
    {
        if ($this->db->row("
            select 1 as one
            from organization_user as ou 
            join user as u on u.user_id = ou.user_id
            where ou.organization_id = ?
                and u.user_email = ?
        ", [
            $this->organization_id,
            $this->organization_invite_email
        ])) {
            $this->addError("base", "Šis lietotājs jau organizacijas loceklis");
        }
    }

    private function validateOrganizationInviteExists(): void
    {
        if ($this->db->row("
            select 1 as one
            from organization_invite
            where organization_id = ?
                and organization_invite_email = ?
        ", [
            $this->organization_id,
            $this->organization_invite_email
        ])) {
            $this->addError("base", "Šis lietotājs jau ir uzaicināts");
        }
    }


}