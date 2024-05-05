<?php

namespace App\Models;

use App\Base\Config;
use App\Base\DataStore;
use App\Base\Message;
use DateTime;
use DateTimeZone;

abstract class Model
{
    const STRING = 1;
    const INTEGER = 2;
    const DECIMAL = 3;
    const DATETIME = 4;
    const DATE = 5;
    const TIME = 6;
    const BOOLEAN = 7;
    const TYPE_MAPPING = [
        'datetime' => self::DATETIME,
        'timestamp' => self::DATETIME,
        'date' => self::DATE,
        'time' => self::TIME,

        'tinyint' => self::INTEGER,
        'smallint' => self::INTEGER,
        'mediumint'    => self::INTEGER,
        'int' => self::INTEGER,
        'integer' => self::INTEGER,
        'bigint' => self::INTEGER,

        'float' => self::DECIMAL,
        'double' => self::DECIMAL,
        'numeric' => self::DECIMAL,
        'decimal' => self::DECIMAL,
        'dec' => self::DECIMAL,

        'string' => self::STRING,

        'boolean' => self::BOOLEAN
    ];
    const FALSE_VALUES = [false, 0, '0', 'f', 'false', 'off'];

    static $attributes_mapping = [];
    protected static ?string $primary_key = null;

    protected Message $msg;
    protected DataStore $db;
    protected Config $config;
    protected ?bool $is_valid = null;

    private array $errors = [];
    private array $attributes = [];
    private array $changed_attributes = [];
    private array $raw_attributes = [];
    private array $dirty_attributes = [];

    public static function all(?array $params = null)
    {
        $db = DataStore::init();

        if (!self::issetTableAndPrimaryKey()) {
            return null;
        }

        if ($data = $db->data('
            select *
            from `' . static::$table_name . '`
        ')) {
            $instances = null;

            foreach ($data as $r) {
                $instances[] = new static($r, true);
            }

            return $instances;
        }

        return null;
    }

    public static function find($id)
    {
        $db = DataStore::init();

        if (!self::issetTableAndPrimaryKey()) {
            return null;
        }

        if ($id && $r = $db->row('
            select *
            from `' . static::$table_name . '`
            where `' . static::$primary_key . '` = ?
        ', $id)) {
            return new static($r, true);
        }

        return null;
    }


    public static function castIntegerSafely($value)
    {
        if (is_int($value)) {
            return $value;
        }

        // Its just a decimal number
        elseif (is_numeric($value) && floor($value) != $value) {
            return (int)$value;
        }

        // If adding 0 to a string causes a float conversion,
        // we have a number over PHP_INT_MAX
        elseif (is_string($value) && trim($value) !== '' && is_float($value + 0)) {
            return (string)$value;
        }

        // If a float was passed and its greater than PHP_INT_MAX
        // (which could be wrong due to floating point precision)
        // We'll also check for equal to (>=) in case the precision
        // loss creates an overflow on casting
        elseif (is_float($value) && $value >= PHP_INT_MAX) {
            return number_format($value, 0, '', '');
        }

        return (int)$value;
    }

    public static function castBooleanSafely($value)
    {
        return !in_array(strtolower(trim($value)), self::FALSE_VALUES);
    }

    public function __construct(
        ?array $attributes = [],
        bool $instantiating_via_find = false
    )
    {
        $this->config = Config::init();
        $this->msg = Message::init();
        $this->db = DataStore::init();

        if ($attributes) {
            $this->setAllowedAttributes($attributes);
        }

        if ($instantiating_via_find) {
            $this->dirty_attributes = [];
            $this->resetChanged();
        }
    }

    public function __set(string $name, mixed $value)
    {
        $method_name = 'set'.str_replace('_', '', ucwords($name, '_'));

        if (method_exists($this, $method_name)) {
            $this->$method_name($value);
        } elseif ($this->hasAttribute($name)) {
            $this->assignAttribute($name, $value);
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
        return $this->readAttribute($name);
    }

    public function __isset(string $name): bool
    {
        $method_name = 'get'.str_replace('_', '', ucwords($name, '_'));

        return method_exists($this, $method_name) ||
                        array_key_exists($name, $this->attributes) ||
                        array_key_exists($name, static::$attributes_mapping);
    }

    public function __clone()
    {
        $this->reset();

        return $this;
    }

    public function reset(): void
    {
        $this->assignAttribute(static::$primary_key, null);

        foreach ($this->attributes as $name => $value) {
            $this->setDirty($name);
            $this->setChangedAttribute($name, $value);
        }
    }

    public function cast(string $name, mixed $value): mixed
    {
        if ($value === null || !$this->hasAttribute($name)) {
            return null;
        }

        $type = $this->getAttributeType($name);

        if (is_string($value) && trim($value) === '' && $type !== self::STRING) {
            return null;
        }

        switch ($type) {
            case self::STRING:
                return (string)$value;
            case self::INTEGER:
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($name, $this->msg->t('error.format'));
                }
                return static::castIntegerSafely($value);
            case self::DECIMAL:
                if (strpos($value, ',') !== false) {
                    $value = str_replace(',', '.', $value);
                }
                if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                    $this->addError($name, $this->msg->t('error.format'));
                }
                return (double)$value;
            case self::DATETIME:
            case self::DATE:
                return $value;
            case self::BOOLEAN:
                return static::castBooleanSafely($value);
        }
        return $value;
    }

    public function getId(): mixed
    {
        if (!isset(static::$primary_key)) {
            return null;
        }
        return $this->readAttribute(static::$primary_key);
    }

    public function attributeWas(string $name): mixed
    {
        if ($this->isAttributeChanged($name)) {
            return $this->changed_attributes[$name];
        }
        if (array_key_exists($name, static::$attributes_mapping)) {
            $value = isset(static::$attributes_mapping[$name]['default']) ?
                $this->cast($name, static::$attributes_mapping[$name]['default']) :
                $value;
            return $value;
        }
        return null;
    }

    public function isChanged(): bool
    {
        return !empty($this->changed_attributes);
    }

    public function isAttributeChanged($name): bool
    {
        return $this->isChanged() &&
            array_key_exists($name, $this->changed_attributes);
    }

    public function getChanged(): ?array
    {
        return $this->isChanged() ?
            array_keys($this->changed_attributes) :
            null;
    }

    public function getChanges(): ?array
    {
        if (!$this->changed_attributes) {
            return null;
        }

        $attributes = null;

        foreach ($this->changed_attributes as $name => $value) {
            $attributes[$name] = [
                $value,
                $this->attributes[$name]
            ];
        }

        return $attributes;
    }

    public function revert(): void
    {
        if ($this->changed_attributes) {
            foreach ($this->changed_attributes as $name => $value) {
                $this->assignAttribute($name, $value);
            }
        }
    }

    public function resetChanged(): void
    {
        $this->changed_attributes = [];
    }

    public function resetChangedAttribute(string $name): void
    {
        if ($this->isAttributeChanged($name)) {
            unset($this->changed_attributes[$name]);
        }
    }

    public function setChangedAttribute(string $name, mixed $value): void
    {
        $this->changed_attributes[$name] = $value;
    }

    public function setDirty(string $name): void
    {
        $this->dirty_attributes[$name] = true;
    }

    public function unsetDirty(string $name): void
    {
        if ($this->dirty_attributes) {
            unset($this->dirty_attributes[$name]);
        }
    }

    public function dirtyAttributes(): ?array
    {
        if (!$this->dirty_attributes)
            return null;

        $dirty = array_intersect_key($this->attributes, $this->dirty_attributes);

        return empty($dirty) ? null : $dirty;
    }

    public function isDirtyAttribute(string $name): bool
    {
        return $this->dirty_attributes &&
            isset($this->dirty_attributes[$name]) &&
            array_key_exists($name, $this->attributes);
    }

    public function isDirty(): bool
    {
        return empty($this->dirty_attributes) ? false : true;
    }

    public function resetDirty(): void
    {
        $this->dirty_attributes = [];
    }

    public function &readAttribute(string $name): mixed
    {
        $value = null; // return reference

        if (array_key_exists($name, $this->attributes)) {
            $value = $this->attributes[$name];
            return $value;
        }
        if (array_key_exists($name, static::$attributes_mapping)) {
            $value = isset(static::$attributes_mapping[$name]['default']) ?
                $this->cast($name, static::$attributes_mapping[$name]['default']) :
                $value;
            return $value;
        }

        $class_name = get_class($this);

        trigger_error("Undefined {$class_name} property {$name}", E_USER_NOTICE);

        return $value;
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function setAttributes(array $attributes): void
    {
        if ($attributes) {
            foreach ($attributes as $name => $value) {
                $this->$name = $value;
            }
        }
    }

    public function setAllowedAttributes(array $attributes): void
    {
        if ($attributes) {
            foreach ($attributes as $name => $value) {
                try {
                    $this->$name = $value;
                } catch (\Exception $e) {
                }
            }
        }
    }

    public function setRawData(array $data): void
    {
        if ($data) {
            foreach ($data as $k => $v) {
                $this->raw_attributes[$k] = $v;
            }
        }
    }

    public function setRawValue(string $name, mixed $value): void
    {
        $this->raw_attributes[$name] = $value;
    }

    public function getRawValue(string $name): mixed
    {
        if (!array_key_exists($name, $this->raw_attributes)) {
            return null;
        }

        return $this->raw_attributes[$name];
    }

    public function getAttributeType(string $name): ?int
    {
        if (
            isset(static::$attributes_mapping[$name]['type']) &&
            isset(self::TYPE_MAPPING[static::$attributes_mapping[$name]['type']])
        ) {
            return self::TYPE_MAPPING[static::$attributes_mapping[$name]['type']];
        }

        return null;
    }

    public function assignAttribute(string $name, mixed $value): mixed
    {
        if (!$this->hasAttribute($name)) {
            return null;
        }

        $value = $this->cast($name, $value);

        if (
            is_null($value) &&
            array_key_exists($name, static::$attributes_mapping) &&
            isset(static::$attributes_mapping[$name]['default'])
        ) {
            $value = $this->cast(
                $name,
                static::$attributes_mapping[$name]['default']
            );
        }

        if ($this->changeAttribute(
            $name,
            $value,
            static::$attributes_mapping[$name]
        )) {
            $this->setDirty($name);
        }

        return $value;
    }

    public function hasAttribute(string $name): bool
    {
        return isset(static::$attributes_mapping[$name]);
    }

    public function &getAttributes(): array
    {
        $attributes = $this->attributes;

        return $attributes;
    }

    public function attributesNames(): array
    {
        return array_keys(static::$attributes_mapping);
    }

    public function allPosibleAttributes(): array
    {
        $result = [];

        foreach($this->attributesNames() as $name) {
            $value = $this->readAttribute($name);
            $result[$name] = is_null($value) ? false : $value;
        }

        return $result;
    }

    public function hasErrors(): bool
    {
        return !!$this->errors;
    }

    public function hasError(string $name): bool
    {
        return isset($this->errors[$name]);
    }

    public function getError(string $name): ?string
    {
        return isset($this->errors[$name]) ? $this->errors[$name] : null;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function getBaseError(): ?string
    {
        return $this->getError('base');
    }

    public function isEmpty(): bool
    {
        foreach (static::$attributes_mapping as $name => $value) {
            if ($this->attributes[$name] ?? null) {
                return false;
            }
        }

        return true;
    }

    public function isValid(?bool $is_trigger = true): bool
    {
        if ($is_trigger && isset($this->is_valid)) {
            return $this->is_valid;
        }

        $this->validate();
        $this->is_valid = !$this->hasErrors();

        return $this->is_valid;
    }

    public function addError(string $name, string $value): void
    {
        $this->errors[$name] = $value;

        if (isset($this->is_valid)) {
            $this->is_valid = false;
        }
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function toParams(): ?array
    {
        return $this->formattedDirtyAttributes();
    }


    protected function validate(): void
    {
    }

    protected function columnFormat(string $name, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        switch ($this->getAttributeType($name)) {
            case self::DATE:
                return $this->formatDate($value);
            case self::DATETIME:
                return $this->formatDateTime($value);
            case self::BOOLEAN:
                return $value ? 1 : 0;
            case self::STRING:
            default:
                return $value;
        }

        return $value;
    }

    protected function getColumns(): array
    {
        $columns = [];

        if ($attributes = $this->dirtyAttributes()) {
            foreach ($attributes as $name => $value) {
                if ($name != static::$primary_key)
                    $columns[$name] = $this->columnFormat($name, $value);
            }
        }

        return $columns;
    }

    protected function createRecord(): bool
    {
        if (!static::$table_name || !static::$primary_key) {
            return false;
        }

        $this->assignCurrentDateTimeString(static::$table_name . '_created_at');
        $this->assignCurrentDateTimeString(static::$table_name . '_updated_at');

        $attributes = $this->getColumns();
        $keys = array_keys($attributes);
        $values = array_values($attributes);

        if ($this->db->query('
            insert into `' . static::$table_name . '` ('.implode(',', $keys).')
            values (' . $this->db->placeholders($keys) . ')
        ', $values)) {
            $this->{static::$primary_key} = $this->db->insertId();
            $this->resetDirty();
            return true;
        }

        return false;
    }

    protected function validateAndCreateRecord(?array $attributes = null): bool
    {
        if ($attributes) {
            $this->setAttributes($attributes);
        }

        if (!$this->isValid()) {
            return false;
        }

        return $this->createRecord();
    }

    protected function updateRecord(): bool
    {
        if (!static::$table_name || !static::$primary_key) {
            return false;
        }

        $this->assignCurrentDateTimeString(static::$table_name . '_updated_at');

        $attributes = $this->getColumns();
        $keys = array_map(function($key){
            return $key . ' = ?';
        }, array_keys($attributes));
        $values = array_values($attributes);
        $values[] = $this->{static::$primary_key};

        if ($this->db->query('
            update `' . static::$table_name . '` set ' . implode(',', $keys) . '
            where ' . static::$primary_key . ' = ?
        ', $values)) {
            $this->resetDirty();
            return true;
        }

        return false;
    }

    protected function validateAndUpdateRecord(?array $attributes = null): bool
    {
        if ($attributes) {
            $this->setAttributes($attributes);
        }
        if (!$this->isValid()) {
            return false;
        }
        if (!$this->isDirty()) {
            return true;
        }

        return $this->updateRecord();
    }

    protected function formatDate(?string $string): ?string
    {
        if (!$string) {
            return null;
        }

        try {
            $datetime = new DateTime($string);
        } catch (Exception $e) {
            return null;
        }

        return $datetime->format('Y-m-d');
    }

    protected function formatDateTime(?string $string): ?string
    {
        if (!$string) {
            return null;
        }

        try {
            $datetime = new DateTime($string);
        } catch (Exception $e) {
            return null;
        }

        return $datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    protected function assignCurrentDateTimeString(string $name): void
    {
        if (!$this->hasAttribute($name)) {
            return;
        }

        if ($this->isDirtyAttribute($name) && $this->readAttribute($name)) {
            return;
        }

        $this->assignAttribute($name, gmdate('c'));
    }

    protected function format(string $name, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        switch ($this->getAttributeType($name)) {
            case self::DATE:
                return $this->formatDate($value);
            case self::DATETIME:
                return $this->formatDateTime($value);
            case self::BOOLEAN:
            case self::STRING:
            default:
                return $value;
        }

        return $value;
    }

    protected function formattedDirtyAttributes(): array
    {
        $formatted_attributes = [];

        if ($attributes = $this->dirtyAttributes()) {
            foreach ($attributes as $name => $value) {
                if ($name != static::$primary_key) {
                    $formatted_attributes[$name] = $this->format($name, $value);
                }
            }
        }

        return $formatted_attributes;
    }


    private static function issetTableAndPrimaryKey(): bool
    {
        if (static::$table_name && static::$primary_key) {
            return true;
        }
        if (!static::$table_name) {
            trigger_error(
                'Undefined static variable table_name for class ' . static::class,
                E_USER_NOTICE
            );
        }
        if (!static::$primary_key) {
            trigger_error(
                'Undefined static variable primary_key for class ' . static::class,
                E_USER_NOTICE
            );
        }

        return false;
    }

    private function changeAttribute(string $name, mixed $value, array $options): bool
    {
        $value_was = $this->attributes[$name] ?? null;

        $this->setAttribute($name, $value);

        if ($options['type'] == 'date') {
            $value = $this->formatDate($value);
        } elseif ($options['type'] == 'datetime') {
            $value = $this->formatDateTime($value);
        }
        if ($value_was === $value) {
            return false;
        }

        $this->setChangedAttribute($name, $value_was);

        return true;
    }
}