<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class GroupUser extends Model
{
    static $attributes_mapping = [
        'group_user_id' => ['type' => 'integer'],
        'group_id' => ['type' => 'integer'],
        'group_user_name' => ['type' => 'string'],
        'group_user_email' => ['type' => 'string'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'group_user';
    protected static ?string $primary_key = 'group_user_id';


    public function create($attributes = null): bool
    {
        if ($attributes) {
            $this->setAttributes($attributes);
        }

        if (!$this->isValid()) {
            return false;
        }

        $this->findAndSetUserId();

        return $this->createRecord();
    }

    public function update($attributes = null): bool
    {
        return $this->validateAndUpdateRecord($attributes);
    }

    public function delete(): bool
    {
        $db = DataStore::init();

        return !!$db->query('
            delete from group_user 
            where group_user_id = ? 
        ', $this->group_user_id);
    }


    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'group_user_name', 'group_user_email'
        ]);

        $presence->validate($this);
    }


    private function findAndSetUserId(): void
    {
        $query = new DataQuery();

        $query
          ->select('user_id')
          ->from('user')
          ->where('user_email = ?', $this->group_user_email);

        $data = $query->fetch();
    
        if ($data) {
            $this->user_id = $data['user_id'];
        }
    }

}