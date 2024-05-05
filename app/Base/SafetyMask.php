<?php

namespace App\Base;

class SafetyMask {

    private array $filter = [];

    const DEFAULT_FILTER = ['password'];
    const FILTER_MASK = '[FILTERED]';

    public function __construct()
    {
        $config = Config::init();

        $this->filter = $config->get('safety_mask', self::DEFAULT_FILTER);
    }

    public function array(mixed $array): array
    {
        if (!is_array($array)) {
            return $array;
        }

        foreach ($array as $name => $value) {
            if (is_string($name) && in_array($name, $this->filter, true)) {
                $array[$name] = self::FILTER_MASK;
            } elseif (is_array($value)) {
                $array[$name] = $this->array($value);
            }
        }

        return $array;
    }

    public function string(mixed $string): mixed
    {
        if (empty($string)) {
            return $string;
        }

        foreach ($this->filter as $value) {
            $pattern = "/(" . $value . "\s?=\s?[\"'])([^\"]+)([\"'])/";
            $string = preg_replace($pattern, '\1'.self::FILTER_MASK.'\3', $string);
        }

        return $string;
    }

    public function url(string $string): string
    {
        foreach ($this->filter as $value) {
            $pattern = "/(".$value."=)([^\&]+)([\&]?)/";
            $string = preg_replace($pattern, '\1'.self::FILTER_MASK.'\3', $string);
        }

        return $string;
    }

    public function xml(string $xml, mixed $tag_names): string
    {
        if (!$xml || !$tag_names) {
            return $xml;
        }

        if (!is_array($tag_names)) {
            $tag_names = [$tag_names];
        }

        foreach ($tag_names as $tag_name) {
            $xml = preg_replace(
                '/(\<' . preg_quote($tag_name) . '\>)(.*)(\<\/' . preg_quote($tag_name) . '\>)/',
                '$1' . self::FILTER_MASK . '$3',
                $xml
            );
        }

        return $xml;
    }

}
