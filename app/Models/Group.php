<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class Group extends Model
{
    static $attributes_mapping = [
        'group_id' => ['type' => 'integer'],
        'group_name' => ['type' => 'string'],
        'organization_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'group';
    protected static ?string $primary_key = 'group_id';


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
            'group_name'
        ]);

        $presence->validate($this);
    }

}