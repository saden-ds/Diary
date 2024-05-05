<?php

namespace App\Validators;

abstract class EachValidator extends Validator
{

    protected array $attributes = [];

    abstract public function validateEach(
        $record,
        string $attribute,
        mixed $value
    ): void;

    public function __construct(array $attributes, ?array $options = [])
    {
        $this->attributes = $attributes;

        parent::__construct($options);
    }

    public function validate($record): void
    {
        foreach ($this->attributes as $attribute) {
            $value = $record->$attribute;

            if (is_null($value) && $this->option('allow_null')) {
                continue;
            }

            if (trim($value ?: '') === '' && $this->option('allow_empty')) {
                continue;
            }

            $this->validateEach($record, $attribute, $value);
        }
    }
}
