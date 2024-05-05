<?php

namespace App\Validators;

use App\Base\Message;

abstract class Validator
{
    protected Config $config;
    protected array $options = [];

    abstract public function validate($record): void;


    public function __construct(array $options = [])
    {
        $this->msg = Message::init();
        $this->options = $options;
    }


    protected function option(string $name): mixed
    {
        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        return null;
    }
}