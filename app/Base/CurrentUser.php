<?php

namespace App\Base;

use App\Middleware\SyncDog\Client;
use App\Models\User;
use Exception;

class CurrentUser
{
    private static ?CurrentUser $instance = null;
    protected Config $config;
    protected Message $msg;
    protected Session $session;
    private bool $is_signed_in = false;
    private ?int $id = null;
    private ?string $email = null;
    private ?string $firstname = null;
    private ?string $lastname = null;
    private ?string $error = null;
    private bool $confirmed = false;
    private bool $active = false;


    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function __wakeup()
    {
        throw new Exception('Cannot unserialize CurrentUser');
    }

    public function __set(string $name, mixed $value)
    {
        $method_name = 'set'.str_replace('_', '', ucwords($name, '_'));

        if (method_exists($this, $method_name)) {
            $this->$method_name($value);
        } else {
            $class_name = get_class($this);

            throw new \Exception("Undefined {$class_name} property {$name}");
        }
    }

    public function &__get(string $name): mixed
    {
        $method_name = 'get'.str_replace('_', '', ucwords($name, '_'));

        if (method_exists($this, $method_name)) {
            $value = $this->$method_name();

            return $value;
        }

        $class_name = get_class($this);

        throw new \Exception("Undefined {$class_name} property {$name}");
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function getConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return !!$this->error;
    }

    public function signIn(string $email, string $password): bool
    {
        if (empty($email)) {
            $this->error = $this->msg->t('authorization.message.error.empty_email');
            return false;
        }

        if (empty($password)) {
            $this->error = $this->msg->t('authorization.message.error.empty_password');
            return false;
        }

        $user = User::findByEmail($email);

        if (!$user || !$user->isEqualsPassword($password)) {
            $this->error = $this->msg->t('authorization.message.error.fail');
            return false;
        }

        return $this->create($user);
    }

    public function isSignedIn(): bool
    {
        return $this->is_signed_in;
    }

    public function signOut(): bool
    {
        return $this->destroy();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'confirmed' => $this->confirmed
        ];
    }

    public function create(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $this->session->set('user', [
            'id' => $user->user_id,
            'email' => $user->user_email,
            'firstname' => $user->user_firstname,
            'lastname' => $user->user_lastname,
            'confirmed' => $user->user_confirmed_at
        ]);
        
        return $this->createFromSession();
        
    }

    public function update(?User $user): bool
    {
        return $this->create($user);
    }

    public function destroy(): bool
    {
        $this->is_signed_in = !$this->session->destroy();

        return $this->is_signed_in;
    }


    private function __construct()
    {
        $this->config = Config::init();
        $this->session = Session::init();
        $this->msg = Message::init();

        $this->createFromSession();
    }

    private function __clone() {}

    private function createFromSession(): bool
    {
        $user = $this->session->get('user');

        if (!$user) {
            return $this->is_signed_in;
        }

        $this->is_signed_in = true;
        $this->id = $user['id'] ?? null;
        $this->email = $user['email'] ?? null;
        $this->firstname = $user['firstname'] ?? null;
        $this->lastname = $user['lastname'] ?? null;
        $this->confirmed = $user['confirmed'] ?? false;
        $this->active = $user['active'] ?? false;

        return $this->is_signed_in;    
    }
}