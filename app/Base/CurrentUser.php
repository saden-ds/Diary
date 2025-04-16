<?php

namespace App\Base;

use App\Base\DataQuery;
use App\Middleware\SyncDog\Client;
use App\Models\User;
use Exception;

class CurrentUser
{   
    public ?string $organization_user_role = null;
    public ?int $organization_id = null;
    public ?string $organization_name = null;
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

    public function selectOrganizationById(?int $organization_id): bool
    {
        $query = new DataQuery();

        $query
            ->select(
                'o.organization_id',
                'o.organization_name',
                'ou.organization_user_role'
            )
            ->from('organization o')
            ->join('organization_user ou on ou.organization_id = o.organization_id')
            ->where('o.organization_id = ?', $organization_id)
            ->where('ou.user_id = ?', $this->id);

        if ($organization_id && $data = $query->fetch()) {
            $this->organization_id = $data['organization_id'];
            $this->organization_name = $data['organization_name'];
            $this->organization_user_role = $data['organization_user_role'];
        } else {
            $this->organization_id = null;
            $this->organization_name = null;
            $this->organization_user_role = null;
        }

        $this->session->set('user', [
            'id' => $this->id,
            'email' => $this->email,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'confirmed' => $this->confirmed,
            'organization_user_role' => $this->organization_user_role,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->organization_name
        ]);

        return $this->createFromSession();
    }

    public function canAdmin($organization_id = null): bool
    {
        if ($organization_id === null) {
            return true;
        }

        return $organization_id == $this->organization_id &&
            $this->organization_user_role === 'admin';
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
            'confirmed' => $this->confirmed,
            'organization_user_role' => $this->organization_user_role,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->organization_name,
            'initials' => $this->getInitials(),
            'fulname_digit' => $this->getFullnameDigit()
        ];
    }

    public function create(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $query = new DataQuery();

        $query
            ->select('ou.organization_user_role, ou.organization_id, o.organization_name')
            ->from('organization_user as ou')
            ->join('organization as o on o.organization_id = ou.organization_id')
            ->where('ou.user_id = ?', $user->user_id);

        if ($r = $query->first()) {
            $organization_user_role = $r['organization_user_role'];
            $organization_id = $r['organization_id'];    
            $organization_name = $r['organization_name'];    
        } else {
            $organization_user_role = null;
            $organization_id = null;
            $organization_name = null;
        }

        $this->session->set('user', [
            'id' => $user->user_id,
            'email' => $user->user_email,
            'firstname' => $user->user_firstname,
            'lastname' => $user->user_lastname,
            'confirmed' => $user->user_confirmed_at,
            'organization_user_role' => $organization_user_role,
            'organization_id' => $organization_id,
            'organization_name' => $organization_name
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

    public function fetchOrganizations(): ?array
    {
        $query = new DataQuery();

        $query
            ->select('o.*')
            ->from('organization_user as ou')
            ->join('organization as o on o.organization_id = ou.organization_id')
            ->where('ou.user_id = ?', $this->id);

        if ($data = $query->fetchAll()) {
            return $data;
        }

        return null;
    }

    private function getInitials(): string
    {
        $user = new User([
            'user_firstname' => $this->firstname,
            'user_lastname' => $this->lastname
        ], true);
        
        return $user->user_initials;
    }

    private function getFullnameDigit(): string
    {
        $user = new User([
            'user_firstname' => $this->firstname,
            'user_lastname' => $this->lastname
        ], true);
        
        return $user->user_digit;
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
        $this->organization_user_role = $user['organization_user_role'] ?? null;
        $this->organization_id = $user['organization_id'] ?? null;
        $this->organization_name = $user['organization_name'] ?? null;

        return $this->is_signed_in;    
    }
}