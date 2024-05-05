<?php

namespace App\Base;

use App\Base\Exceptions\DataStoreException;
use App\Base\Config;
use App\Base\Logger;
use PDO;
use PDOException;

class DataStore {
	const DRIVER = 'mysql';
	const HOST = '127.0.0.1';
	const DATABASE = 'database';
	const PDO_OPTIONS = [
		// PDO::ATTR_CASE => PDO::CASE_LOWER,
		// PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		// PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
		// PDO::ATTR_STRINGIFY_FETCHES => false
	];

	private static array $instances = [];
	private $connection, $config, $logger;

	public static function init($database = null): DataStore
	{
		if (!isset(self::$instances[$database])) {
			self::$instances[$database] = new self($database);
		}
		return self::$instances[$database];
	}

	public static function reconnect(): void
	{
		foreach (self::$instances as $db) {
			$db->connection = null;
		}
	}

	public function __wakeup()
	{
		throw new DataStoreException('Cannot unserialize DataStore');
	}

	public function __destruct()
	{
		if ($this->connection) {
			$this->connection = null;
		}
	}

	public function query(string $sql, mixed $values = null)
	{
		$timestamp = microtime(true);

		if ($values && !is_array($values)) {
			$values = [$values];
		}

		try {
			if (!$sth = $this->connection->prepare($sql)) {
				$this->log($timestamp, $sql, $values, $this->getSthErrorMessage($sth));

				throw new DataStoreException($this->getSthErrorMessage($sth));
			}
		} catch (PDOException $e) {
			$this->log($timestamp, $sql, $values, $this->getSthErrorMessage($sth));

			throw new DataStoreException($e);
		}

		$sth->setFetchMode(PDO::FETCH_ASSOC);

		try {
			if (!$sth->execute($values)) {
				$this->log($timestamp, $sql, $values, $this->getSthErrorMessage($sth));

				throw new DataStoreException($this->getSthErrorMessage($sth));
			}
		} catch (PDOException $e) {
			$this->log($timestamp, $sql, $values, $this->getSthErrorMessage($sth));

			throw new DataStoreException($e);
		}

		if ($this->isSlowQuery($timestamp)) {
			$this->logSlowQuery($timestamp, $sql, $values);
		}

		$this->log($timestamp, $sql, $values);

		return $sth;
	}

	public function data(string $sql, mixed $values = null, Closure $handler = null)
	{
		$sth = $this->query($sql, $values);
		$flag = false;

		if (!$handler) {
			return $sth->rowCount() ? $sth->fetchAll(PDO::FETCH_ASSOC) : null;
		}

		while (($row = $sth->fetch(PDO::FETCH_ASSOC))) {
			$flag = true;
			$handler($row);
		}

		return $flag;
	}

	public function row($sql, $values = null)
	{
		$sth = $this->query($sql, $values);

		return $sth->fetch(PDO::FETCH_ASSOC);
	}

	public function pluck($sql, $values, $index)
	{
		$data = [];
		$sth = $this->query($sql, $values);

		while (($row = $sth->fetch(PDO::FETCH_ASSOC))) {
			$data[] = $row[$index];
		}

		return $data;
	}

	public function insertId()
	{
		return $this->connection->lastInsertId();
	}

	public function placeholders($keys): string
	{
		return str_repeat('?,', count($keys) - 1) . '?';
	}


	private function __construct(?string $database = null)
	{
		$config = Config::init();
		$database = $database ?: $config->get('databases.default') ?: self::DATABASE;
		$this->config = $config->get('databases.' . $database);
		$this->logger = new Logger($this->config['log'] ?? 'mysql');
		$driver = self::DRIVER;
		$host = self::HOST;

		if (!isset($this->config)) {
			throw new ConfigException("Database $database not configured");
		}
		if (isset($this->config['driver']) && trim($this->config['driver'] ?: '')) {
			$driver = $this->config['driver'];
		}
		if (isset($this->config['host']) && trim($this->config['host'] ?: '')) {
			$host = $this->config['host'];
		}

		$dsn = $driver . ':';
		$dsn .= 'dbname=' . $database . ';';
		$dsn .= 'host=' . $host . ';';
		$dsn .= 'charset=UTF8;';

		$this->config['dsn'] = $dsn;
		$this->connect();
	}

	private function __clone()
	{

	}

	private function connect()
	{
		try {
			$this->connection = new PDO(
				$this->config['dsn'],
				$this->config['username'],
				$this->config['password'],
				self::PDO_OPTIONS
			);
			$this->logger->info('CONNECT');
		} catch (PDOException $e) {
			throw new DataStoreException($e);
		}
	}

	private function interpolateQuery(string $sql, mixed $values = null): string
	{
		if (!$values) {
			return $sql;
		}

		$keys = [];
		$replace_limit = 1;

		foreach ($values as $key => $value) {
			if (is_string($key)) {
				$keys[] = '/:'.$key.'/';
				$replace_limit = -1;
			} else {
				$keys[] = '/[?]/';
			}

			if (is_string($value)) {
				$values[$key] = '"' . $value . '"';
			} elseif (is_array($value)) {
				$values[$key] = '"' . implode("','", $value) . '"';
			} elseif (is_null($value)) {
				$values[$key] = 'NULL';
			}
		}

		$sql = preg_replace($keys, $values, $sql, $replace_limit);

		return $sql;
	}

	private function getSthErrorMessage($sth): string
	{
		if (!is_array($sth->errorInfo())) {
			return 'Error';
		}

		return implode(' ', $sth->errorInfo());
	}

	private function log(float $timestamp, string $sql, mixed $values, ?string $error = null): void
	{
		$log = $this->getQueryFilePath();
		$log .= ' ' . $this->logger->getTime($timestamp) . PHP_EOL;
		$log .= $this->interpolateQuery($sql, $values);

		$this->logger->info($log);

		if ($error) {
			$this->logger->warn($log . PHP_EOL . $error);
		}
	}

	private function logSlowQuery(float $timestamp, string $sql, mixed $values, ?string $error = null): void
	{
		$logger = new Logger($this->config['log'] . '-slow');

		$log = $this->getQueryFilePath();
		$log .= ' ' . $logger->getTime($timestamp) . PHP_EOL;
		$log .= $this->interpolateQuery($sql, $values);

		$logger->info($log);
	}

	private function getQueryFilePath(): ?string
	{
		if (!$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) {
			return null;
		}

		$ignore = ['Base\DataQuery', 'Base\DataStore'];
		$message = null;


		foreach ($debug as $r) {
			$file = $r['file'] ?? null;
			$line = $r['line'] ?? null;
			$class = $r['class'] ?? null;

			if (in_array($class, $ignore)) {
				continue;
			}

			if ($file) {
				$message = preg_replace(
					'/^' . preg_quote(__ROOT__ . '/', '/') . '/', '', $file
				);

				if (isset($r['line'])) {
					$message .= ':' . $r['line'];
				}

				break;
			}
		}

		return $message;
	}

	public function isSlowQuery(float $timestamp): bool
	{
		if (
			!isset($this->config['slow_log_time']) ||
			!$this->config['slow_log_time']
		) {
			return false;
		}

		return (microtime(true) - $timestamp) * 1000 > $this->config['slow_log_time'];
	}
}