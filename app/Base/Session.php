<?php

namespace App\Base;

class Session extends Singleton
{
    const ALGO = 'sha512';
    const PREFIX = 'SESSION';
    const ID_LENGTH = 128;
    const HASH_PATTERN = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const HASH_LENGTH = 64;
    const DEFAULT_NAME = 'domain_com';
    const DEFAULT_COOKIE_DOMAIN = 'localhost';
    const DEFAULT_EXPIRE = 86400;
    const DEFAULT_SECRET = 'efb7681f4f4bbf0402bdf555028d0e625258f5d3c950f6ece158e31453844de4767d2588d8a20481d20853718aff64916eee61b12f843f14f03b409a07d94285';

    private ?string $id = null;
    private ?string $ip = null;
    private array $data = [];
    private array $config = [];
    private MemcacheStore $storage;


    public function __construct()
    {
        $config = Config::init();

        $this->storage = MemcacheStore::init();
        $this->config = [
            'name' => $config->get('session.name') ?: self::DEFAULT_NAME,
            'cookie_domain' => $config->get('cookie_domain') ?: self::DEFAULT_COOKIE_DOMAIN,
            'expire' => $config->get('session.expire') ?: self::DEFAULT_EXPIRE,
            'secret' => $config->get('secret') ?: self::DEFAULT_SECRET
        ];

        if (isset($_COOKIE[$this->config['name']])) {
            $this->id = $_COOKIE[$this->config['name']];
        }

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
        }

        $this->check();
    }

    public function __destruct()
    {
        if ($this->id) {
            $this->set('time', time());
            $this->storage->set(
                $this->key($this->id),
                $this->data,
                $this->config['expire']
            );
        }
    }

    public function &get(string $name): mixed
    {
        $value = $this->data[$name] ?? null;

        return $value;
    }

    public function set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function delete(string $name): bool
    {
        if (isset($this->data[$name])) {
            unset($this->data[$name]);
        }

        return true;
    }

    public function destroy(): bool
    {
        if ($this->id) {
            $this->storage->delete($this->key($this->id));
        }

        $this->id = null;
        $this->data = [];

        return true;
    }

    public function isValidCsrf(?string $csrf): bool
    {
        return $csrf && $csrf === $this->get('csrf');
    }


    private function key(string $id): string
    {
        return $this->config['name'] . self::PREFIX . $id;
    }

    private function check(): void
    {
        if ($this->id && mb_strlen($this->id) === self::ID_LENGTH) {
            $this->data = $this->storage->get($this->key($this->id)) ?: [];
        }

        if (!$this->data) {
            $this->create();
        }

        setcookie(
            $this->config['name'],
            $this->id,
            time() + $this->config['expire'],
            '/',
            $this->config['cookie_domain']
        );
    }

    private function create(): bool
    {
        $this->id = $this->generateId();
        $this->data = [
            'session_id' => $this->id,
            'ip' => $this->ip,
            'csrf' => $this->secret($this->hash())
        ];
        return !!$this->id;
    }

    private function generateId(): string
    {
        $id = $this->secret($this->hash());

        while ($this->storage->get($this->key($id))) {
            $id = $this->secret($this->hash());
        }

        return $id;
    }

    private function hash(
        ?string $length = self::HASH_LENGTH,
        ?string $pattern = self::HASH_PATTERN
    ): string
    {
            $result = '';
            $pattern_length = strlen($pattern);
            for ($i = 0; $i < $length; $i++){
                $result .= $pattern[rand(0, $pattern_length - 1)];
            }
            return $result;
    }

    private function secret(string $token): string
    {
        return hash(self::ALGO, $this->config['secret'] . $token);
    }

}
