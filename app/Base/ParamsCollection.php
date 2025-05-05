<?php

namespace App\Base;

use ArrayAccess;
use DateTime;
use DateTimeZone;

class ParamsCollection implements ArrayAccess
{
    const KEYS_DELIMITER = '.';

    private array $data = [];


    public function __construct($data = [])
    {
        $this->data = $data ?: [];
    }

    public function get(string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->data;
        }

        if ($this->exists($key)) {
            return $this->data[$key];
        }

        if (strpos($key, self::KEYS_DELIMITER) === false) {
            return $default;
        }

        return $this->getRecursive(explode(self::KEYS_DELIMITER, $key), $default);
    }

    public function getRecursive(array $keys, $default = null)
    {
        $data = $this->data;

        foreach ($keys as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return $default;
            }

            $data = $data[$key];
        }
        return $data;
    }

    public function getDate($name): ?string
    {
        if (!$value = $this->get($name)) {
            return $value;
        }

        if (!$datetime = $this->getDateTimeObject($value)) {
            return null;
        }

        return $datetime->format('Y-m-d');
    }

    public function getEndOfDate($name): ?string
    {
        if (!$value = $this->get($name)) {
            return $value;
        }

        if (!$datetime = $this->getDateTimeObject($value)) {
            return null;
        }

        $datetime->setTime(23, 59, 59);

        return $datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function getDateTime($name): ?string
    {
        if (!$value = $this->get($name)) {
            return $value;
        }

        if (!$datetime = $this->getDateTimeObject($value)) {
            return null;
        }

        return $datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function getDateTimeObject($value) {
        try {
            $datetime = new DateTime($value);
        } catch (Exception $e) {
            return null;
        }

        return $datetime;
    }

    public function set(string $name, $value)
    {
        $this->data[$name] = $value;
    }

    public function unset(string $name)
    {
        if ($this->exists($name)) {
            unset($this->data[$name]);
        }
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function can(string $key): bool
    {
        return !!$this->get($key);
    }

    public function has(string $key): bool
    {
        return !!$this->get($key);
    }

    public function isTrue(string $key): bool
    {
        return !!$this->get($key);
    }

    public function isEqual(string $key, $value): bool
    {
        return $this->get($key) == $value;
    }

    public function isEmpty(string $key): bool
    {
        $value = $this->get($key);

        if (is_array($value)) {
            return empty($value);
        }

        return $value === null || trim($value) === '';
    }

    public function isDateTime(string $key): bool
    {
        return strtotime($this->get($key)) !== false;
    }

    /**
   *    ArrayAccess interface
   */

    public function offsetExists(mixed $key): bool
    {
        return $this->exists($key);
    }

    public function offsetGet(mixed $key): mixed
    {
        return $this->get($key);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        if (is_null($key)) {
            $this->data[] = $value;

            return;
        }

        $this->set($key, $value);
    }

    public function offsetUnset(mixed $key): void
    {
        unset($this->data[$key]);
    }

}
