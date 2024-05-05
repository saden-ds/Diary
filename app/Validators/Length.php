<?php

namespace App\Validators;

class Length extends EachValidator
{

    const CHECKS = ['is', 'minimum', 'maximum'];
    const MESSAGES = [
        'is' => 'error.wrong_length',
        'minimum' => 'error.too_short',
        'maximum' => 'error.too_long'
    ];


    public function validateEach($record, string $name, mixed $value): void
    {
        $value_length = mb_strlen(strval($value));

        foreach (self::CHECKS as $check) {
            $check_value = $this->option($check);
            $is_erorr = true;

            if (!is_numeric($check_value)) {
                continue;
            }

            switch ($check) {
                case 'is':
                    if ($value_length === $check_value) {
                        $is_erorr = false;
                    }
                    break;
                case 'minimum':
                    if ($value_length >= $check_value) {
                        $is_erorr = false;
                    }
                    break;
                case 'maximum':
                    if ($value_length <= $check_value) {
                        $is_erorr = false;
                    }
                    break;
                default:
                    $is_erorr = false;
            }

            if (!$is_erorr) {
                continue;
            }

            $message_key = self::MESSAGES[$check] ?? 'error.length';

            $record->addError($name, $this->msg->t($message_key, [
                'count' => $check_value
            ]));
        }
    }
}