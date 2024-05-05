<?php

namespace App\Base;

class Storage extends Singleton
{
	const DEFAULT_NAME = 'domain_com';

	private string $name;
	private MemcacheStore $storage;


	public function __construct() {
		$config = Config::init();
		$this->storage = MemcacheStore::init();
		$this->name = $config->get('session.name', self::DEFAULT_NAME);
	}

	public function get(string $key): string
	{
		return $this->storage->get($this->key($key));
	}

	public function set(string $key, mixed $value, int $expire = 0): bool
	{
		return $this->storage->set($this->key($key), $value, $expire);
	}

	public function delete(string $key): bool
	{
		return $this->storage->delete($this->key($key));
	}


	private function key(string $key): string
	{
		return $this->name . $key;
	}
}