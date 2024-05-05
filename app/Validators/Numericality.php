<?php

namespace App\Validators;

class Numericality extends EachValidator
{

    const CHECKS = [
        'greater_than', 'greater_than_or_equal_to',
        'equal_to', 'less_than', 'less_than_or_equal_to',
        'other_than'
    ];
    const MESSAGES = [
        'greater_than' => 'error.greater_than',
        'greater_than_or_equal_to' => 'error.greater_than_or_equal_to',
        'equal_to' => 'error.equal_to',
        'less_than' => 'error.less_than',
        'less_than_or_equal_to' => 'error.less_than_or_equal_to',
        'other_than' => 'error.other_than',
        'not_a_number' => 'error.not_a_number',
        'not_an_integer' => 'error.not_an_integer'
    ];


    public function validateEach($record, string $name, mixed $value): void
    {
        if (!is_numeric($value)) {
            $record->addError(
                $name, $this->msg->t(self::MESSAGES['not_a_number'])
            );
            return;
        }

        if (
            $this->option('only_integer') &&
            filter_var($value, FILTER_VALIDATE_INT) === false
        ) {
            $record->addError(
                $name,
                $this->msg->t(self::MESSAGES['not_an_integer'])
            );
            return;
        }

        foreach (self::CHECKS as $check) {
            $check_value = $this->option($check);
            $is_erorr = true;

            if (!is_numeric($check_value)) {
                continue;
            }

            switch ($check) {
                case 'greater_than':
                    if ($value > $check_value) {
                        $is_erorr = false;
                    }
                    break;
                case 'greater_than_or_equal_to':
                    if ($value >= $check_value) {
                        $is_erorr = false;
                    }
                    break;
                case 'equal_to':
                    if ($value == $check_value) {
                        $is_erorr = false;
                    }
                    break;
                case 'less_than':
                    if ($value < $check_value) {
                        $is_erorr = false;
                    }
                    break;
                case 'less_than_or_equal_to':
                    if ($value <= $check_value) {
                        $is_erorr = false;
                    }
                    break;
                case 'other_than':
                    if ($value != $check_value) {
                        $is_erorr = false;
                    }
                    break;
                default:
                    $is_erorr = false;
            }

            if (!$is_erorr) {
                continue;
            }

            $message_key = self::MESSAGES[$check] ?? 'error.numericality';

            $record->addError($name, $this->msg->t($message_key, [
                'count' => $check_value
            ]));
        }
    }
}