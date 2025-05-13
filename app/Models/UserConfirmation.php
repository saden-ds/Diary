<?php

namespace App\Models;

use App\Base\DataQuery;
use App\Base\TokenGenerator;
use App\Mailers\User as UserMailer;
use App\Models\User;
use App\Validators\Presence as ValidatorPresence;

class UserConfirmation extends Model
{
    static $attributes_mapping = [
        'user_confirmation_id' => ['type' => 'integer'],
        'user_confirmation_token' => ['type' => 'string'],
        'user_confirmation_created_at' => ['type' => 'datetime'],
        'user_id' => ['type' => 'string']
    ];

    protected static ?string $table_name = 'user_confirmation';
    protected static ?string $primary_key = 'user_confirmation_id';


    public function createAndSendMail(User $user): bool
    {
        $query = new DataQuery();
        $mailer = new UserMailer();

        $query
            ->from('user_confirmation')
            ->where('user_id = ?', $user->user_id);

        $this->user_id = $user->user_id;

        if ($attributes = $query->fetch()) {
            $this->setAttributes($attributes);
            $this->touch();
        } elseif (!$this->create()) {
            return false;
        }

        if (!filter_var($user->user_email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return $mailer->sendConfirmation($user, $this->user_confirmation_token);
    }

    public function create($attributes = null): bool
    {
        if ($attributes) {
            $this->setAttributes($attributes);
        }

        if (!$this->isValid()) {
            return false;
        }

        $this->user_confirmation_token = $this->generateToken();

        return $this->createRecord();
    }

    public function touch() {
        return !!$this->db->query('
            update user_confirmation set user_confirmation_created_at = utc_timestamp()
            where user_confirmation_id = ?
        ', $this->user_confirmation_id);
    }

    public function delete(): bool
    {
        $this->db->query('
            delete from user_confirmation
            where user_confirmation_created_at < utc_timestamp() - interval 1 week
        ');

        return !!$this->db->query('
            delete from user_confirmation
            where user_id = ?
        ', $this->user_id);
    }


    protected function validate(): void
    {
        $presence = new ValidatorPresence([
            'user_id'
        ]);

        $presence->validate($this);
    }


    private function generateToken() {
        $generator = new TokenGenerator();
        $token = $generator->getSecret($generator->getHash(128));

        while (!!$this->db->row('
            select 1 as one from user_confirmation
            where user_confirmation_token = ?
        ', $token)) {
            $token = $generator->getSecret($generator->getHash(128));
        }
        return $token;
    }
}