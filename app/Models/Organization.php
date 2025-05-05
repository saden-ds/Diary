<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\DataStore;
use App\Validators\Presence as ValidatorPresence;

class Organization extends Model
{
    static $attributes_mapping = [
        'organization_id' => ['type' => 'integer'],
        'organization_name' => ['type' => 'string'],
        'user_id' => ['type' => 'integer']
    ];

    protected static ?string $table_name = 'organization';
    protected static ?string $primary_key = 'organization_id';


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
            'organization_name'
        ]);

        $presence->validate($this);
    }

}