<?php

namespace App\Base;

use App\Base\Number;
use Exception;

class Config {

    const NAME_DELIMITER = '.';
    const DEFAULT_LOCALE = 'en';

    private static Config $instance;
    private array $options = [];
    private string $env = 'development';

    public static function init($option = null): Config
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($option);
        }
        return self::$instance;
    }

    public function &__get($name): mixed
    {
        $value = null;
        $method_name = 'get'.str_replace('_', '', ucwords($name, '_'));

        if (method_exists($this, $method_name)) {
            $value = $this->$method_name();
            return $value;
        }

        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        trigger_error("Undefined option: {$name}", E_USER_NOTICE);

        return $value;
    }

    public function add($options): void
    {
        $this->options = array_replace_recursive($this->options, $options);
    }

    public function get($name = null, $default = null): mixed
    {
        if (is_null($name)) {
            return $this->options;
        }

        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        if (strpos($name, self::NAME_DELIMITER) === false) {
            return $default;
        }

        return $this->recursive(explode(self::NAME_DELIMITER, $name), $default);
    }

    public function getUploadMaxFilesize(bool $raw = false): ?string
    {
        $value = $this->parsePhpIniSize(ini_get('upload_max_filesize'));

        if (!$value) {
            $value = null;
        } elseif(!$raw) {
            $value = Number::set($value)->toPrettyFileSize();
        }

        return $value;
    }

    public function getPostMaxSize(bool $raw = false): ?string
    {
        $value = $this->parsePhpIniSize(ini_get('post_max_size'));

        if (!$value) {
            $value = null;
        } elseif(!$raw) {
            $value = Number::set($value)->toPrettyFileSize();
        }

        return $value;
    }

    public function recursive($names, $default = null): mixed
    {
        $options = $this->options;

        foreach ($names as $name) {
            if (!is_array($options) || !array_key_exists($name, $options)) {
                return $default;
            }

            $options = $options[$name];
        }

        return $options;
    }

    public function isEnv($name): bool
    {
        return $this->env === $name;
    }

    public function setLocale($value): void
    {
        if (in_array($value, $this->get('locales'))) {
            $this->options['locale'] = $value;
        }
    }

    public function getLocale(): string
    {
        return $this->get('locale') ?:
            $this->get('default_locale') ?:
            self::DEFAULT_LOCALE;
    }


    private function __construct($options = null)
    {
        $this->env = ENV;

        if ($options) {
            $this->add($options);
        }

        $this->add($this->envOptions());

        if ($this->uid && $this->gid) {
            $u = posix_getpwnam($this->uid);
            $g = posix_getgrnam($this->gid);
            posix_setgid($g['gid']);
            posix_setuid($u['uid']);
        }
    }

    private function __clone() {}

    private function envOptions(): array
    {
        $path = __ROOT__.'/config/'.$this->env.'.yml';

        try {
            if (
                function_exists('yaml_parse') &&
                file_exists($path) &&
                $yaml = file_get_contents($path)
            ) {
                return yaml_parse($yaml);
            }
        } catch (Exception $e) {
            throw new Exception('Config file not found');
        }

        throw new Exception('Config file not found');
    }

    private function parsePhpIniSize(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return round($size);
    }

}
