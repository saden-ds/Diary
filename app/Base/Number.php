<?php

namespace App\Base;

class Number
{
    private Message $msg;
    private mixed $value = null;

    public static function set($value = null): Number
    {
        return new self($value);
    }


    public function __construct(mixed $value = null)
    {
        $this->msg = Message::init();

        if ($value === null || trim($value) === '') {
            $this->value = null;
        } else {
            $this->value = $value ? $value + 0 : 0;
        }
    }

    public function __toString()
    {
        return $this->value;
    }

    public function toPrettyFileSize(?int $precision = 2): string
    {
        $units = [
            $this->msg->t('number.storage.units.b', ['default' => 'B']),
            $this->msg->t('number.storage.units.kb', ['default' => 'kB']),
            $this->msg->t('number.storage.units.mb', ['default' => 'MB']),
            $this->msg->t('number.storage.units.gb', ['default' => 'GB']),
            $this->msg->t('number.storage.units.tb', ['default' => 'TB']),
            $this->msg->t('number.storage.units.pb', ['default' => 'PB']),
            $this->msg->t('number.storage.units.eb', ['default' => 'EB']),
            $this->msg->t('number.storage.units.zb', ['default' => 'ZB']),
            $this->msg->t('number.storage.units.yb', ['default' => 'YB'])
        ];
        $size = $this->value ?: 0;
        $step = 1024;
        $i = 0;

        while (($size / $step) > 0.9) {
            $size = $size / $step;
            $i++;
        }

        return round($size, $precision) . ($units[$i] ?? '');
    }
}