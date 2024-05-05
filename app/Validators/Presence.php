<?php

namespace App\Validators;

class Presence extends EachValidator
{
    public function validateEach($record, string $name, mixed $value): void
    {
        if (is_array($value)) {
            $is_empty = empty($value);
        } else {
            $is_empty = trim($value ?: '') === '';
        }

        if ($is_empty) {
            $record->addError($name, $this->msg->t('error.blank'));
        }
    }
}