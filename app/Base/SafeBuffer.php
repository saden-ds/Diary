<?php

namespace App\Base;

class SafeBuffer
{

    const JS_ESCAPE_MAP = [
        '\\' => '\\\\',
        "</" => '<\/',
        "\r\n" => '\n',
        "\n" => '\n',
        "\r" => '\n',
        '"' => '\\"',
        "'" => "\\'",
    ];

    private string $value = '';

    public function __construct($value)
    {
        if (is_array($value) || is_object($value)) {
            $this->value = '';
        } else {
            $this->value = strval($value);
        }
    }

    public function __toString()
    {
        return $this->value;
    }

    public function escapeJavascript()
    {
        if (!$this->value) {
            return null;
        }

        $pattern = '/(\\|<\/|\r\n|\342\200\250|\342\200\251|[\n\r"\'])/u';
        $result = preg_replace_callback($pattern, function ($match) {
            return self::JS_ESCAPE_MAP[$match[0]];
        },                              $this->value);

        return $result;
    }

    public function h()
    {
        return $this->htmlEscape();
    }

    public function htmlEscape()
    {
        return htmlspecialchars($this->value);
    }

    public function j()
    {
        return $this->escapeJavascript();
    }

    public static function set($value)
    {
        return new self($value);
    }

}