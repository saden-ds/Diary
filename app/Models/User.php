<?php

namespace App\Models;

use App\Base\TokenGenerator;
use App\Base\DataQuery;
use App\Validators\Presence as ValidatorPresence;

class User extends Model
{
    static $attributes_mapping = [
        'user_id' => ['type' => 'integer'],
        'user_active' => ['type' => 'boolean', 'default' => false],
        'user_confirmed_at' => ['type' => 'datetime'],
        'user_email' => ['type' => 'string'],
        'user_firstname' => ['type' => 'string'],
        'user_lastname' => ['type' => 'string'],
        'user_encrypted_password' => ['type' => 'string'],
        'user_salt' => ['type' => 'string']
    ];

    protected static ?string $table_name = 'user';
    protected static ?string $primary_key = 'user_id';
    public $user_password_repeat;
    private $user_password;

    public static function findByEmail($email): ?User
    {
        $query = new DataQuery;

        $query
            ->from('user')
            ->where('user_email = ?', $email);

        if (!$email || !$data = $query->fetch()) {
            return null;
        }

        return new self($data, true);
    }

    public function setUserFullname($value): void
    {
        $parts = explode(' ',$value);

        $this->user_firstname = $parts[0] ?? null;
        $this->user_lastname = $parts[1] ?? null;
    }

    public function getUserFullname() {
        $fullname = $this->user_firstname;

        if ($this->user_lastname) {
            $fullname .= ($fullname ? ' ' : '') . $this->user_lastname;
        }

        return $fullname;
    }

    public function getUserInitials(): string
    {
        $name = $this->getUserFullname();
        
        if (!$name) {
            return '';
        }

        $name = trim(preg_replace('/\(.*\)/', '', $name));
        $words = explode(' ', $name, 2);
        $initials = '';

        if ($words) {
            foreach ($words as $w) {
                $initials .= mb_substr($w, 0, 1);
            }
        }

        return mb_strtoupper($initials);
    }

    public function getUserDigit(): int
    {
        $name = $this->getUserFullname();

        if (!$name) {
            return 1;
        }

        return crc32($name) % 10;
    }

    public function create($attributes = null): bool
    {
        return $this->validateAndCreateRecord($attributes);
    }

    public function update($attributes = null): bool
    {
        return $this->validateAndUpdateRecord($attributes);
    }

    public function isEqualsPassword($password): bool
    {
        return !strcmp(
            $this->encryptPassword($password, $this->user_salt),
            $this->user_encrypted_password
        );
    }

    public function setUserPassword($value): void
    {
        if ($value) {
            $this->user_password = $value;
            $this->setUserPasswordAttributes();
        }
    }

    protected function validate(): void
    {   
        $presence = new ValidatorPresence([
            'user_firstname', 'user_lastname', 'user_email'
        ]);

        $presence->validate($this);

        if ($this->user_email && !filter_var($this->user_email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('user_email', $this->msg->t('error.email.format'));
        }

        if (!$this->user_id || $this->user_password) {
            if (!$this->user_password_repeat) {
                $this->addError('user_password_repeat', 'ir jābūt aizpildītam');
            } elseif ($this->user_password_repeat != $this->user_password) {
                $this->addError('user_password_repeat', 'nav vienāds');
            }
        }

        if (!$this->user_id && !$this->hasErrors()) {
            $this->validateUniquiness();
        }
    }


    private function validateUniquiness(): void
    {
        $query = new DataQuery();

        $query
            ->select('1 as one')
            ->from('user')
            ->where('user_email = ?', $this->user_email);

        if ($data = $query->first()) {
            $this->addError('base', $this->msg->t('user.message.error.already_exists'));
        }
    }

    private function setUserPasswordAttributes(): void
    {
        if ($this->isValidUserPassword()) {
            $generator = new TokenGenerator();
            $this->assignAttribute('user_salt',
                base64_encode($generator->getHash(14))
            );
            $this->assignAttribute('user_encrypted_password', $this->encryptPassword(
                $this->user_password, $this->user_salt
            ));
        }
    }

    private function isValidUserPassword(): bool
    {
        $this->validateUserPassword();

        return !$this->hasError('user_password');
    }

    private function encryptPassword($password, $salt) 
    {
        return base64_encode(hash_hmac(
            'sha256',
            base64_decode($salt).iconv('UTF-8', 'UTF-16LE', $password),
            base64_decode($salt),
            true
        ));
    }

    private function validateUserPassword(): void
    {
        // if (!$this->user_password) {
        //     $this->addError('user_password', $this->msg->t('error.blank'));
        //     return;
        // }

        // $min = $this->config->get('user.password.min') ?: 8;
        // $max = $this->config->get('user.password.max') ?: 72;

        // if (strlen($this->user_password) < $min) {
        //     $this->addError('user_password', $this->msg->t('error.password.min', [
        //         'count' => $min
        //     ]));
        // } elseif (strlen($this->user_password) > $max) {
        //     $this->addError('user_password', $this->msg->t('error.password.max', [
        //         'count' => $max
        //   ]));
        // }

        if (!$this->user_password) {
            $this->addError('user_password', $this->msg->t('error.blank'));
            return;
        }

        $length = mb_strlen(strval($this->user_password));
        $min = $this->config->get('user.password.min') ?: 9;
        $max = $this->config->get('user.password.max') ?: 72;
        $complexity_pattern = '/(?=.*?[a-z])(?=.*?[A-Z])(?=.*?[0-9])(?=.*?[#?!@$%^&*_=\-])/';

        if ($min && $length < $min) {
            $this->addError('user_password', $this->msg->t('error.password.min', [
                'count' => $min
            ]));
            return;
        }

        if ($max && $length > $max) {
            $this->addError('user_password', $this->msg->t('error.password.max', [
                'count' => $max
            ]));
            return;
        }

        if (!preg_match($complexity_pattern, $this->user_password)) {
            $this->addError(
                'user_password',
                $this->msg->t('error.password.complexity')
            );
            return;
        }
    }
}