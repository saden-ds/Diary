<?php

namespace App\Base;

use Exception;
use Memcached;

class MemcacheStore extends Singleton
{
	private Config $config;
	private Memcached $memcache;


	public function get(string $key): mixed
	{
		return $this->memcache->get($key);
	}

	public function set(string $key, mixed $value, int $expire = 0): bool
	{
		return $this->memcache->set($key, $value, $expire);
	}

	public function delete(string $key): bool
	{
		return $this->memcache->delete($key);
	}


	protected function __construct()
	{
		$this->config = Config::init();
		$this->memcache = new Memcached();

		if (!$this->memcache->addServer($this->getHost(), $this->getPort())) {
			throw new Exception('Memcache server error');
		}
	}


	private function getHost(): string
	{
		if ($host = $this->config->get('memcache.host')) {
			return $host;
		}
		throw new Exception('Memcache host not configured');
	}

	private function getPort(): int|string
	{
		if ($port = $this->config->get('memcache.port')) {
			return $port;
		}
		throw new Exception('Memcache port not configured');
	}
}